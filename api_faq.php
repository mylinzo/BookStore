<?php
header('Content-Type: application/json; charset=utf-8');

// Error handling - output errors as JSON
ini_set('display_errors', 0);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $errstr
    ]);
    exit;
});

set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $exception->getMessage()
    ]);
    exit;
});

session_start();
@include 'koneksi.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Get all categories
if ($action === 'categories' && $method === 'GET') {
    try {
        $query = "SELECT id_category, category_name, category_slug, category_icon, category_description, 
                         (SELECT COUNT(*) FROM help_articles WHERE category_id = help_categories.id_category AND is_active = TRUE) as article_count
                  FROM help_categories 
                  WHERE is_active = TRUE 
                  ORDER BY sort_order ASC";
        
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }
        
        $categories = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $categories]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get articles by category
if ($action === 'articles' && $method === 'GET') {
    try {
        $category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
        
        if (empty($category)) {
            throw new Exception('Category parameter diperlukan');
        }
        
        // Get category ID from slug
        $cat_query = "SELECT id_category FROM help_categories WHERE category_slug = ? AND is_active = TRUE";
        
        if ($stmt = mysqli_prepare($conn, $cat_query)) {
            mysqli_stmt_bind_param($stmt, "s", $category);
            mysqli_stmt_execute($stmt);
            $cat_result = mysqli_stmt_get_result($stmt);
            
            if ($cat_row = mysqli_fetch_assoc($cat_result)) {
                $category_id = $cat_row['id_category'];
                
                // Get articles
                $article_query = "SELECT id_article, category_id, question, answer, article_slug, 
                                        views_count, helpful_count, unhelpful_count
                                 FROM help_articles 
                                 WHERE category_id = ? AND is_active = TRUE 
                                 ORDER BY sort_order ASC";
                
                if ($stmt2 = mysqli_prepare($conn, $article_query)) {
                    mysqli_stmt_bind_param($stmt2, "i", $category_id);
                    mysqli_stmt_execute($stmt2);
                    $article_result = mysqli_stmt_get_result($stmt2);
                    
                    $articles = [];
                    while ($row = mysqli_fetch_assoc($article_result)) {
                        $articles[] = $row;
                    }
                    
                    echo json_encode(['success' => true, 'data' => $articles]);
                    
                    mysqli_stmt_close($stmt2);
                } else {
                    throw new Exception('Error prepare statement: ' . mysqli_error($conn));
                }
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Category tidak ditemukan']);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception('Error prepare statement: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get article detail
if ($action === 'article-detail' && $method === 'GET') {
    try {
        $article_slug = isset($_GET['slug']) ? mysqli_real_escape_string($conn, $_GET['slug']) : '';
        
        if (empty($article_slug)) {
            throw new Exception('Article slug diperlukan');
        }
        
        $query = "SELECT ha.*, hc.category_name, hc.category_slug
                  FROM help_articles ha
                  LEFT JOIN help_categories hc ON ha.category_id = hc.id_category
                  WHERE ha.article_slug = ? AND ha.is_active = TRUE";
        
        if ($stmt = mysqli_prepare($conn, $query)) {
            mysqli_stmt_bind_param($stmt, "s", $article_slug);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($article = mysqli_fetch_assoc($result)) {
                // Increment view count
                $update_query = "UPDATE help_articles SET views_count = views_count + 1 WHERE id_article = ?";
                if ($stmt2 = mysqli_prepare($conn, $update_query)) {
                    mysqli_stmt_bind_param($stmt2, "i", $article['id_article']);
                    mysqli_stmt_execute($stmt2);
                    mysqli_stmt_close($stmt2);
                }
                
                echo json_encode(['success' => true, 'data' => $article]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Article tidak ditemukan']);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception('Error prepare statement: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Search articles
if ($action === 'search' && $method === 'GET') {
    try {
        $search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
        
        if (strlen($search) < 2) {
            throw new Exception('Minimal 2 karakter untuk search');
        }
        
        if (strlen($search) > 100) {
            throw new Exception('Maksimal 100 karakter untuk search');
        }
        
        $search_pattern = '%' . $search . '%';
        
        $query = "SELECT ha.*, hc.category_name, hc.category_slug
                  FROM help_articles ha
                  LEFT JOIN help_categories hc ON ha.category_id = hc.id_category
                  WHERE ha.is_active = TRUE 
                  AND (ha.question LIKE ? OR ha.answer LIKE ?)
                  ORDER BY CASE 
                           WHEN ha.question LIKE ? THEN 1
                           WHEN ha.answer LIKE ? THEN 2
                           ELSE 3
                           END,
                           ha.views_count DESC
                  LIMIT 20";
        
        if ($stmt = mysqli_prepare($conn, $query)) {
            mysqli_stmt_bind_param($stmt, "ssss", $search_pattern, $search_pattern, $search_pattern, $search_pattern);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $articles = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $articles[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $articles, 'count' => count($articles)]);
            
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception('Error prepare statement: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Submit feedback
if ($action === 'feedback' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $article_id = $data['article_id'] ?? 0;
        $is_helpful = $data['is_helpful'] ?? null;
        $feedback_text = $data['feedback_text'] ?? '';
        
        if ($article_id <= 0) {
            throw new Exception('Article ID tidak valid');
        }
        
        if ($is_helpful === null) {
            throw new Exception('is_helpful parameter diperlukan');
        }
        
        $user_id = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : null;
        
        // Insert feedback
        $query = "INSERT INTO help_feedback (article_id, user_id, is_helpful, feedback_text) 
                  VALUES (?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $query)) {
            $is_helpful_int = $is_helpful ? 1 : 0;
            $feedback_text = !empty($feedback_text) ? mysqli_real_escape_string($conn, $feedback_text) : null;
            
            mysqli_stmt_bind_param($stmt, "ibis", $article_id, $user_id, $is_helpful_int, $feedback_text);
            
            if (mysqli_stmt_execute($stmt)) {
                // Update article helpful/unhelpful count
                if ($is_helpful) {
                    $update_query = "UPDATE help_articles SET helpful_count = helpful_count + 1 WHERE id_article = ?";
                } else {
                    $update_query = "UPDATE help_articles SET unhelpful_count = unhelpful_count + 1 WHERE id_article = ?";
                }
                
                if ($stmt2 = mysqli_prepare($conn, $update_query)) {
                    mysqli_stmt_bind_param($stmt2, "i", $article_id);
                    mysqli_stmt_execute($stmt2);
                    mysqli_stmt_close($stmt2);
                }
                
                http_response_code(201);
                echo json_encode(['success' => true, 'message' => 'Feedback berhasil dikirim']);
            } else {
                throw new Exception('Gagal menyimpan feedback');
            }
            
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception('Error prepare statement: ' . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Default response
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
?>
