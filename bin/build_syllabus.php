<?php
$text = file_get_contents(dirname(__DIR__) . '/textbook.md');

// Code blocks
$text = preg_replace_callback('/```(?:php|javascript|css)?\n(.*?)```/ms', function($m) {
    return '$$$CODE_BLOCK_' . base64_encode($m[1]) . '$$$';
}, $text);

// Inline code
$text = preg_replace_callback('/`(.*?)`/', function($m) {
    return '<code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
}, $text);

// Bold
$text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
// Links
$text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $text);

// Pre-process headings
$text = preg_replace('/^(#{1,6})\s+(.*?)$/m', "\n$1 $2\n", $text);

$lines = explode("\n", $text);
$html = [];
$in_list = false;
$list_type = '';
$p_open = false;
$in_blockquote = false;
$toc_links = [];

foreach ($lines as $line) {
    $line = trim($line);
    
    // Close blocks on empty lines
    if ($line === '') {
        if ($p_open) { $html[] = '</p>'; $p_open = false; }
        if ($in_list) { $html[] = '</' . $list_type . '>'; $in_list = false; }
        if ($in_blockquote) { $html[] = '</blockquote>'; $in_blockquote = false; }
        continue;
    }

    // HR
    if ($line === '---') {
        if ($p_open) { $html[] = '</p>'; $p_open = false; }
        if ($in_list) { $html[] = '</' . $list_type . '>'; $in_list = false; }
        if ($in_blockquote) { $html[] = '</blockquote>'; $in_blockquote = false; }
        $html[] = '<hr>';
        continue;
    }

    // Headings
    if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
        if ($p_open) { $html[] = '</p>'; $p_open = false; }
        if ($in_list) { $html[] = '</' . $list_type . '>'; $in_list = false; }
        if ($in_blockquote) { $html[] = '</blockquote>'; $in_blockquote = false; }
        
        $level = strlen($m[1]);
        $content = $m[2];
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', strip_tags($content)));
        $slug = trim($slug, '-');
        
        if ($level === 1) {
            $html[] = '<h1 class="syllabus-title">' . $content . '</h1>';
        } elseif ($level === 2) {
            $html[] = '<div class="chapter-module" id="' . $slug . '"><h2 class="chapter-title">' . $content . '</h2>';
            if (strpos(strtolower($content), 'module') !== false) {
                $toc_links[] = ['slug' => $slug, 'title' => $content];
            }
        } elseif ($level === 3) {
            $html[] = '<h3>' . $content . '</h3>';
        } elseif ($level === 4) {
            $html[] = '<h4>' . $content . '</h4>';
        } elseif ($level === 5) {
            $html[] = '<h5>' . $content . '</h5>';
        } elseif ($level === 6) {
            $html[] = '<h6>' . $content . '</h6>';
        }
        continue;
    }

    // Ordered List
    if (preg_match('/^(\d+)\.\s+(.*)$/', $line, $m)) {
        if ($p_open) { $html[] = '</p>'; $p_open = false; }
        if ($in_blockquote) { $html[] = '</blockquote>'; $in_blockquote = false; }
        
        if (!$in_list || $list_type !== 'ol') {
            if ($in_list) { $html[] = '</' . $list_type . '>'; }
            $html[] = '<ol class="syllabus-list">';
            $in_list = true;
            $list_type = 'ol';
        }
        $html[] = '<li>' . $m[2] . '</li>';
        continue;
    }

    // Unordered List
    if (preg_match('/^[\-\*]\s+(.*)$/', $line, $m)) {
        if ($p_open) { $html[] = '</p>'; $p_open = false; }
        if ($in_blockquote) { $html[] = '</blockquote>'; $in_blockquote = false; }
        
        if (!$in_list || $list_type !== 'ul') {
            if ($in_list) { $html[] = '</' . $list_type . '>'; }
            $html[] = '<ul class="syllabus-list">';
            $in_list = true;
            $list_type = 'ul';
        }
        $html[] = '<li>' . $m[1] . '</li>';
        continue;
    }

    // Blockquote
    if (preg_match('/^>\s?(.*)$/', $line, $m)) {
        if ($p_open) { $html[] = '</p>'; $p_open = false; }
        if ($in_list) { $html[] = '</' . $list_type . '>'; $in_list = false; }
        
        if (!$in_blockquote) {
            $html[] = '<blockquote class="syllabus-quote">';
            $in_blockquote = true;
        }
        
        $content_in = $m[1];
        if (preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]/', $content_in, $alert)) {
            $html[] = '<strong class="alert-tag">' . ucfirst(strtolower($alert[1])) . ':</strong><br>';
            continue;
        }
        
        $html[] = $content_in . '<br>';
        continue;
    }

    // Regular paragraph text
    if ($in_blockquote) { $html[] = '</blockquote>'; $in_blockquote = false; }
    if ($in_list) { $html[] = '</' . $list_type . '>'; $in_list = false; }
    
    // Code block placeholder
    if (strpos($line, '$$$CODE_BLOCK_') === 0) {
        if ($p_open) { $html[] = '</p>'; $p_open = false; }
        $html[] = $line;
        continue;
    }

    if (!$p_open) {
        $html[] = '<p>';
        $p_open = true;
    } else {
        $html[] = '<br>';
    }
    $html[] = $line;
}

if ($p_open) { $html[] = '</p>'; }
if ($in_list) { $html[] = '</' . $list_type . '>'; }
if ($in_blockquote) { $html[] = '</blockquote>'; }

// Wrap modules
$final_html = [];
$in_chapter = false;
foreach ($html as $tag) {
    if (strpos($tag, '<div class="chapter-module"') === 0) {
        if ($in_chapter) { $final_html[] = '</div>'; }
        $in_chapter = true;
    }
    $final_html[] = $tag;
}
if ($in_chapter) { $final_html[] = '</div>'; }

$output = implode("\n", $final_html);

// Restore code blocks
$output = preg_replace_callback('/\$\$\$CODE_BLOCK_(.*?)\$\$\$/', function($m) {
    return '<pre><code>' . htmlspecialchars(base64_decode($m[1]), ENT_QUOTES, 'UTF-8') . '</code></pre>';
}, $output);

// Generate Sidebar
$sidebar = '<div class="syllabus-sidebar"><details class="syllabus-toc" open><summary class="toc-summary"><h3>Chapters</h3></summary><ul>';
foreach ($toc_links as $link) {
    $title = preg_replace('/^Module \d+: /', '', $link['title']);
    $mod = preg_match('/^Module (\d+)/', $link['title'], $m) ? $m[1] : '';
    $sidebar .= '<li><a href="#' . $link['slug'] . '"><span class="toc-num">' . $mod . '</span> <span class="toc-title">' . $title . '</span></a></li>';
}
$sidebar .= '</ul></details></div>';

$view = <<<VIEW
<?php
/**
 * Title: Syllabus View
 * Purpose: Renders the textbook / syllabus page.
 */
\$pageTitle = \$data['title'] ?? 'Architectural Syllabus | Magma Framework';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(\$pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="/css/components/syllabus.css">
</head>
<body class="welcome-page">
    <div class="welcome-container">
<div class="welcome-hero syllabus-hero">
    <div class="container">
        <h1 class="welcome-hero__title">Architectural Syllabus</h1>
        <p class="welcome-hero__subtitle syllabus-hero__subtitle">A Masterclass in Enterprise Software Architecture. This textbook is a comprehensive, iteratively compiled record of our deep dives into the explicit, vanilla PHP/JS architecture that powers Magma.</p>
    </div>
</div>
<main class="syllabus-page">
    <div class="container syllabus-layout">
        $sidebar
        <div class="syllabus-content">
            $output
        </div>
    </div>
</main>
        <footer class="welcome-footer">
            <p>Magma Framework &bull; Educational Architecture Core &bull; Debian Linux &bull; Strict SOLID Architecture</p>
        </footer>
    </div>
</body>
</html>
VIEW;

file_put_contents(dirname(__DIR__) . '/app/views/syllabus.php', $view);
echo "Syllabus view regenerated successfully.\n";
