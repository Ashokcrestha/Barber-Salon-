<?php
/**
 * ClassicCuts Barbershop - Public Staff API Endpoint
 * Provides dynamic list of staff members with photos, roles, experience, and bios for front-end pages.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$staff = [];

if ($db_connected) {
    try {
        if ($pdo) {
            $stmt = $pdo->query("SELECT id, name, role, experience_years, bio, photo FROM staff WHERE account_status = 'approved' OR account_status IS NULL ORDER BY experience_years DESC, id ASC");
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($mysqli) {
            $res = $mysqli->query("SELECT id, name, role, experience_years, bio, photo FROM staff WHERE account_status = 'approved' OR account_status IS NULL ORDER BY experience_years DESC, id ASC");
            $staff = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }
    } catch (Exception $e) {
        $staff = [];
    }
}

// Default fallback staff list if table is empty
if (empty($staff)) {
    $staff = [
        ['id' => 1, 'name' => 'Ram Bahadur',     'role' => 'Master Barber',          'experience_years' => 8, 'bio' => 'Expert in skin fades & hair styling',           'photo' => 'images/team/1.jpg'],
        ['id' => 2, 'name' => 'Shyam Kumar',     'role' => 'Senior Stylist',         'experience_years' => 5, 'bio' => 'Specialist in razor shave & beard shape up',   'photo' => 'images/team/2.jpg'],
        ['id' => 3, 'name' => 'Ramesh Shrestha', 'role' => 'Barber & Facial Specialist', 'experience_years' => 3, 'bio' => 'Expert in facial & head massage',          'photo' => 'images/team/3.jpg'],
        ['id' => 4, 'name' => 'Hari Sharma',     'role' => 'Junior Barber & Stylist','experience_years' => 2, 'bio' => 'Specialist in kids haircuts & scissor cuts',  'photo' => 'images/team/4.jpg'],
    ];
}

// Clean photo paths & sanitize text output
foreach ($staff as &$s) {
    $s['photo'] = !empty($s['photo']) ? $s['photo'] : 'images/team/1.jpg';
    $s['bio']   = !empty($s['bio']) ? $s['bio'] : 'Professional styling specialist.';
    $s['role']  = !empty($s['role']) ? $s['role'] : 'Barber';
}

echo json_encode([
    'result' => 'success',
    'staff' => $staff
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
