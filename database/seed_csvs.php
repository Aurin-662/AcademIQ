<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$fCerts = fopen(database_path('data/certificates_sample.csv'), 'w');
fputcsv($fCerts, ['certificate_id','institution_code','institution_name','student_name','roll_no','department','passing_year','result_grade','status','hash','issue_date']);

$fLogs = fopen(database_path('data/verification_logs_sample.csv'), 'w');
fputcsv($fLogs, ['certificate_id','verified_by','verification_type','result','ip_address','timestamp']);

$institutions = [
    'KUET' => 'Khulna University of Engineering & Technology',
    'BUET' => 'Bangladesh University of Engineering and Technology',
    'RUET' => 'Rajshahi University of Engineering & Technology',
    'CUET' => 'Chittagong University of Engineering & Technology',
    'DU'   => 'University of Dhaka',
];

$names = ['Mohammad Karim', 'Tanvir Ahmed', 'Aurin Rahman', 'Sadia Afrin', 'Rakibul Hasan', 'Nafis Iqbal', 'Tahmid Hossain', 'Zubair Al Mahmud', 'Sumaiya Islam', 'Shakil Khan'];
$departments = ['Computer Science and Engineering', 'Electrical and Electronic Engineering', 'Mechanical Engineering', 'Civil Engineering'];

$certIds = [];

for ($i = 1; $i <= 200; $i++) {
    $code = array_rand($institutions);
    $name = $institutions[$code];
    $student = $names[array_rand($names)] . ' ' . $i;
    $roll = (string)(1807000 + $i);
    $year = rand(2018, 2024);
    $cgpa = number_format(rand(320, 395) / 100, 2);
    $docId = 'CERT-' . $year . '-' . $code . '-' . str_pad($i, 6, '0', STR_PAD_LEFT);
    $status = ($i % 10 === 0) ? 'flagged' : 'active';
    
    // Original plain hash or invalid hash for flagged
    $hash = ($status === 'flagged') ? 'intentional_tampered_hash_value_12345' : \App\Models\DocumentRecord::computeHash($docId, $student, $code, $roll, $year);
    $date = date('Y-m-d', strtotime('-' . rand(10, 500) . ' days'));
    
    fputcsv($fCerts, [$docId, $code, $name, $student, $roll, $departments[array_rand($departments)], $year, $cgpa, $status, $hash, $date]);
    $certIds[] = $docId;
}
fclose($fCerts);

$results = ['genuine', 'genuine', 'genuine', 'tampered', 'not_found'];
for ($j = 1; $j <= 500; $j++) {
    $cId = ($j % 8 === 0) ? 'UNKNOWN-CERT-' . rand(1000, 9999) : $certIds[array_rand($certIds)];
    $res = ($j % 8 === 0) ? 'not_found' : $results[array_rand($results)];
    $by = ($j % 3 === 0) ? 'Brain Station 23 PLC' : (($j % 3 === 1) ? 'Optimizely Dhaka' : '192.168.1.' . rand(2, 50));
    $type = ($j % 4 === 0) ? 'bulk' : 'single';
    $ip = '103.25.12.' . rand(1, 250);
    $daysAgo = rand(0, 29);
    $hoursAgo = rand(0, 23);
    $time = date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days -' . $hoursAgo . ' hours'));
    
    fputcsv($fLogs, [$cId, $by, $type, $res, $ip, $time]);
}
fclose($fLogs);

echo "Sample CSV files generated in database/data/ successfully!\n";
