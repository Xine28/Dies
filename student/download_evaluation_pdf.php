<?php
include "../includes/auth.php";
checkRole('student', '../student/login.php');

include "../config/database.php";

function pdfEscape($text) {
    $text = (string) $text;
    $text = str_replace("\\", "\\\\", $text);
    $text = str_replace("(", "\\(", $text);
    $text = str_replace(")", "\\)", $text);
    return preg_replace('/[^\x20-\x7E]/', '?', $text);
}

function wrapPdfText($text, $maxChars = 56) {
    $text = trim((string) $text);
    if ($text === '') {
        return ['-'];
    }

    $words = preg_split('/\s+/', $text);
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : ($current . ' ' . $word);
        if (strlen($candidate) <= $maxChars) {
            $current = $candidate;
        } else {
            if ($current !== '') {
                $lines[] = $current;
            }
            while (strlen($word) > $maxChars) {
                $lines[] = substr($word, 0, $maxChars);
                $word = substr($word, $maxChars);
            }
            $current = $word;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }

    return empty($lines) ? ['-'] : $lines;
}

function loadLogoAsJpeg($logoPath) {
    if (!file_exists($logoPath)) {
        return null;
    }
    if (!function_exists('imagecreatefrompng') || !function_exists('imagejpeg')) {
        return null;
    }

    $img = @imagecreatefrompng($logoPath);
    if (!$img) {
        return null;
    }

    $width = imagesx($img);
    $height = imagesy($img);

    ob_start();
    imagejpeg($img, null, 90);
    $jpegData = ob_get_clean();
    imagedestroy($img);

    if ($jpegData === false || $jpegData === '') {
        return null;
    }

    return [
        'data' => $jpegData,
        'width' => $width,
        'height' => $height
    ];
}

function buildFormalPdf(array $rows, $companyName, $logo = null) {
    $content = "";

    // Header with optional logo
    if ($logo !== null) {
        $logoW = 56;
        $ratio = ($logo['width'] > 0) ? ($logo['height'] / $logo['width']) : 1;
        $logoH = $logoW * $ratio;
        $content .= "q\n{$logoW} 0 0 {$logoH} 50 730 cm\n/Im1 Do\nQ\n";
    }

    $titleX = ($logo !== null) ? 120 : 50;
    $content .= "BT\n/F1 18 Tf\n{$titleX} 760 Td\n(" . pdfEscape($companyName) . ") Tj\nET\n";
    $content .= "BT\n/F1 13 Tf\n{$titleX} 738 Td\n(Student Evaluation Report) Tj\nET\n";

    // Horizontal separator under header
    $content .= "50 712 m 562 712 l S\n";

    // Table geometry
    $x = 50;
    $tableWidth = 512;
    $labelWidth = 175;
    $valueWidth = $tableWidth - $labelWidth;
    $currentTop = 690;

    foreach ($rows as $row) {
        $label = (string) ($row['label'] ?? '');
        $valueLines = $row['value_lines'] ?? ['-'];
        if (!is_array($valueLines) || empty($valueLines)) {
            $valueLines = ['-'];
        }

        $lineCount = max(1, count($valueLines));
        $rowHeight = 14 + ($lineCount * 14);
        $yBottom = $currentTop - $rowHeight;

        // Cells
        $content .= $x . " " . $yBottom . " " . $labelWidth . " " . $rowHeight . " re S\n";
        $content .= ($x + $labelWidth) . " " . $yBottom . " " . $valueWidth . " " . $rowHeight . " re S\n";

        // Label text
        $content .= "BT\n/F1 11 Tf\n" . ($x + 8) . " " . ($currentTop - 16) . " Td\n(" . pdfEscape($label) . ") Tj\nET\n";

        // Value text
        $valueY = $currentTop - 16;
        foreach ($valueLines as $idx => $line) {
            $content .= "BT\n/F1 11 Tf\n" . ($x + $labelWidth + 8) . " " . ($valueY - ($idx * 14)) . " Td\n(" . pdfEscape($line) . ") Tj\nET\n";
        }

        $currentTop = $yBottom;
    }

    // Footer note
    $content .= "BT\n/F1 9 Tf\n50 40 Td\n(This is a system-generated report.) Tj\nET\n";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $resources = "/Font << /F1 4 0 R >>";
    if ($logo !== null) {
        $resources .= " /XObject << /Im1 6 0 R >>";
    }
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << {$resources} >> /Contents 5 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";
    if ($logo !== null) {
        $objects[] = "6 0 obj\n<< /Type /XObject /Subtype /Image /Width " . (int) $logo['width'] . " /Height " . (int) $logo['height'] . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($logo['data']) . " >>\nstream\n" . $logo['data'] . "\nendstream\nendobj\n";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }

    $xrefPos = strlen($pdf);
    $count = count($objects) + 1;
    $pdf .= "xref\n0 {$count}\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i < $count; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
    return $pdf;
}

$studentId = (int) ($_SESSION['user']['id'] ?? 0);
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($studentId <= 0 || $id <= 0) {
    header('Location: dashboard.php?error=invalid');
    exit;
}

$ev = false;
try {
    $stmt = $conn->prepare(
        "SELECT e.*, u.full_name AS intern_name, u.email AS intern_email,
                CASE WHEN s.full_name IS NULL OR s.full_name = '' THEN 'Supervisor' ELSE s.full_name END AS supervisor_name
         FROM evaluations e
         INNER JOIN users u ON e.intern_id = u.id
         LEFT JOIN users s ON e.supervisor_id = s.id
         WHERE e.id = ? AND e.intern_id = ?
         LIMIT 1"
    );
    $stmt->execute([$id, $studentId]);
    $ev = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Student download evaluation fetch error: ' . $e->getMessage());
    $ev = false;
}

if (!$ev) {
    header('Location: dashboard.php?error=not_found');
    exit;
}

$score = isset($ev['score']) ? (int) $ev['score'] : 0;
$result = ($score <= 50) ? 'Failed' : 'Passed';
$internDisplayName = !empty($ev['intern_name']) ? $ev['intern_name'] : ('Intern #' . (int) ($ev['intern_id'] ?? 0));
$evalDateRaw = $ev['eval_date'] ?? $ev['created_at'] ?? date('Y-m-d');
$evalDate = date('M d, Y', strtotime($evalDateRaw));
$comments = trim((string) ($ev['comments'] ?? ''));
$rows = [
    ['label' => 'Intern Name', 'value_lines' => wrapPdfText($internDisplayName, 55)],
    ['label' => 'Intern Email', 'value_lines' => wrapPdfText($ev['intern_email'] ?? '-', 55)],
    ['label' => 'Supervisor', 'value_lines' => wrapPdfText($ev['supervisor_name'] ?? 'Supervisor', 55)],
    ['label' => 'Evaluation Date', 'value_lines' => [$evalDate]],
    ['label' => 'Communication', 'value_lines' => [($ev['comm'] ?? '-') . ' / 5']],
    ['label' => 'Problem Solving', 'value_lines' => [($ev['problem'] ?? '-') . ' / 5']],
    ['label' => 'Teamwork', 'value_lines' => [($ev['teamwork'] ?? '-') . ' / 5']],
    ['label' => 'Overall Score', 'value_lines' => [$score . '%']],
    ['label' => 'Result', 'value_lines' => [$result]],
    ['label' => 'Comments', 'value_lines' => wrapPdfText($comments === '' ? '-' : $comments, 55)],
];

$logo = loadLogoAsJpeg(__DIR__ . '/../images/logo.png');
$pdf = buildFormalPdf($rows, 'Digital Internship Evaluation System', $logo);
$studentName = trim((string) ($ev['intern_name'] ?? 'student'));
$safeName = preg_replace('/[^A-Za-z0-9 _-]/', '', $studentName);
$safeName = preg_replace('/\s+/', ' ', $safeName);
$safeName = trim($safeName);
if ($safeName === '') {
    $safeName = 'student';
}
$filename = 'evaluation_(' . $safeName . ').pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
?>

