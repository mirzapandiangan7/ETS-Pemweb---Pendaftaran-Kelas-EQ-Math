<?php
/**
 * Proses Pembayaran
 * EQ - Math - Pendaftaran Kelas Matematika
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireRole('siswa');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('../dashboard/student/index.php', 'Metode tidak diizinkan', 'error');
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Get form data
$jadwalId = $_POST['jadwal_id'] ?? null;

if (!$jadwalId) {
    redirectWithMessage('../dashboard/student/pilih-kelas.php', 'Pilih jadwal kelas terlebih dahulu', 'error');
}

try {
    // Get jadwal info
    $jadwal = $db->fetchOne(
        "SELECT jk.*, mk.nama_kelas, mk.harga
        FROM jadwal_kelas jk
        JOIN master_kelas mk ON mk.id = jk.kelas_id
        WHERE jk.id = ?",
        [$jadwalId]
    );

    if (!$jadwal) {
        throw new Exception('Jadwal tidak ditemukan');
    }

    // Check if user already has this class
    $existing = $db->fetchOne(
        "SELECT tp.* FROM transaksi_pembayaran tp
        WHERE tp.user_id = ? AND tp.jadwal_id = ? AND tp.status_pembayaran = 'settlement'",
        [$userId, $jadwalId]
    );

    if ($existing) {
        throw new Exception('Anda sudah terdaftar di kelas ini');
    }

    // Check if there's a pending transaction for this schedule
    $pending = $db->fetchOne(
        "SELECT tp.* FROM transaksi_pembayaran tp
        WHERE tp.user_id = ? AND tp.jadwal_id = ? AND tp.status_pembayaran = 'pending'",
        [$userId, $jadwalId]
    );

    if ($pending) {
        // Process the pending transaction
        $transaksiId = $pending['id'];
    } else {
        // Generate order ID
        $orderId = generateInvoice();

        // Create new transaction record
        $transaksiId = $db->insert('transaksi_pembayaran', [
            'order_id' => $orderId,
            'user_id' => $userId,
            'jadwal_id' => $jadwalId,
            'jumlah_bayar' => $jadwal['harga'],
            'status_pembayaran' => 'pending'
        ]);
    }

    // Simulate payment processing (auto-approve for demo)
    $paymentSuccess = true; // In production, integrate with payment gateway

    if ($paymentSuccess) {
        $db->update(
            'transaksi_pembayaran',
            ['status_pembayaran' => 'settlement'],
            'id = ?',
            [$transaksiId]
        );

        redirectWithMessage('../dashboard/student/index.php', 'Pembayaran berhasil! Selamat belajar di ' . $jadwal['nama_kelas']);
    } else {
        redirectWithMessage('../dashboard/student/pembayaran.php?jadwal_id=' . $jadwalId, 'Pembayaran pending. Silakan selesaikan pembayaran.', 'warning');
    }
} catch (Exception $e) {
    redirectWithMessage('../dashboard/student/pilih-kelas.php', $e->getMessage(), 'error');
}
?>
