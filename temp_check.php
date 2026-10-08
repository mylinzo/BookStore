<?php
include 'koneksi.php';
$res = mysqli_query($conn, 'SELECT id_pesanan,id_pembeli,status,created_at FROM pesanan LIMIT 5');
while($r=mysqli_fetch_assoc($res)){
    echo implode('|',$r) . "\n";
}
mysqli_close($conn);
