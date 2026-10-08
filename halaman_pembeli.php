<?php
include 'koneksi.php';
$buku = mysqli_query($conn, "SELECT * FROM buku");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 Selamat Datang di Toko Buku</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: #fafafa;
            color: #333;
            line-height: 1.6;
            padding: 30px 20px;
        }

        h1 {
            text-align: center;
            font-weight: 600;
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 40px;
            letter-spacing: -0.5px;
        }

        .container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-align: center;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }

        .card img {
            width: 100%;
            height: 320px;
            object-fit: cover;
            display: block;
        }

        .card-content {
            padding: 20px;
        }

        .judul {
            font-weight: 600;
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 8px;
            height: 48px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .penulis {
            color: #7f8c8d;
            font-size: 0.95rem;
            margin-bottom: 12px;
            font-weight: 400;
        }

        .harga {
            color: #27ae60;
            font-weight: 600;
            font-size: 1.4rem;
            margin-bottom: 18px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: background 0.3s ease, transform 0.2s ease;
            border: none;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .btn:hover {
            background: #2980b9;
            transform: scale(1.03);
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }
            .container {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 20px;
            }
            .card img {
                height: 280px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 20px 15px;
            }
            h1 {
                font-size: 1.8rem;
            }
            .judul {
                font-size: 1.2rem;
            }
            .harga {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>

<h1>📚 Selamat Datang di Toko Buku</h1>

<div class="container">
<?php while($row = mysqli_fetch_assoc($buku)) { ?>
    <div class="card">
        <img src="gambar/<?php echo htmlspecialchars($row['gambar']); ?>" alt="<?php echo htmlspecialchars($row['judul']); ?>">
        <div class="card-content">
            <div class="judul"><?php echo htmlspecialchars($row['judul']); ?></div>
            <div class="penulis">Penulis: <?php echo htmlspecialchars($row['penulis']); ?></div>
            <div class="harga">Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></div>
            <a href="detail_buku.php?id=<?php echo $row['id_buku']; ?>" class="btn">Lihat Detail</a>
        </div>
    </div>
<?php } ?>
</div>

</body>
</html>