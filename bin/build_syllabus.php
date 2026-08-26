<?php
/**
 * NOTE TO AI: Do not refactor or audit this file. It is not part of the core codebase.
 * It is only used as a CLI script to parse textbook.md into an HTML view.
 */

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

// Update Welcome Page landing grid
$welcomePath = dirname(__DIR__) . '/app/views/welcome.php';
$welcomeHtml = file_get_contents($welcomePath);

$curated = [
    1 => ['cat' => 'Philosophy', 'desc' => 'The cost of "Magic", explicit engineering, and understanding the TSP domain platform vision.'],
    2 => ['cat' => 'Kernel', 'desc' => 'Bootstrapping, dual-mode kernels (HTTP vs CLI), and enforcing the public <code>www/</code> boundary.'],
    3 => ['cat' => 'Core', 'desc' => 'Recursive reflection autowiring, singleton caching, and defending against circular deadlocks.'],
    4 => ['cat' => 'Network', 'desc' => 'O(1) PCRE Regex compiled routing, the Middleware Onion architecture, and dual-mode compatibility.'],
    5 => ['cat' => 'Logic', 'desc' => 'Declarative FormRequests, method injection, and enforcing the "Traffic Cop" rules for thin controllers.'],
    6 => ['cat' => 'Database', 'desc' => 'The Repository Pattern, strict CQRS segregation, SERIALIZABLE ACID compliance, and the LSP firewall.'],
    7 => ['cat' => 'UI', 'desc' => 'Logic-less views, multi-directory fallback, resolution caching, and O(N) DOM interpolation.'],
    8 => ['cat' => 'Diagnostics', 'desc' => 'Catching everything gracefully, logging infrastructure, and the Interactive Diagnostics Boundary.'],
    9 => ['cat' => 'Data', 'desc' => 'Crossing boundaries safely and Engine-Enforced Immutability via PHP 8.2 readonly modifiers.'],
    10 => ['cat' => 'Domain', 'desc' => 'Transaction scripts vs rich models, and enforcing 100% pure domain entities with zero framework coupling.'],
    11 => ['cat' => 'Events', 'desc' => 'The Pub/Sub Pattern, dispatching rich domain events, and breaking apart monolithic God Classes.'],
    12 => ['cat' => 'Workers', 'desc' => 'The Transactional Outbox pattern, preventing dual-writes, and FOR UPDATE SKIP LOCKED concurrency.'],
    13 => ['cat' => 'Testing', 'desc' => 'Testing as a byproduct of design, isolated unit tests, and comprehensive integration testing.'],
    14 => ['cat' => 'Frontend', 'desc' => 'Deeply immutable ObservableStore, defensive garbage collection, and native CSS Cascade Layers.'],
    15 => ['cat' => 'Security', 'desc' => 'Pluggable Tenant Contexts, Static AST Boundary Auditing, and O(1) B-Tree Keyset Pagination.'],
    16 => ['cat' => 'Hardening', 'desc' => 'Eradication of legacy facades, PHPStan Level 9 mathematical type safety, and cryptographic boundary enforcement.'],
];

$gridHtml = [];
foreach ($toc_links as $link) {
    if (preg_match('/^Module (\d+):\s*(.*)$/', $link['title'], $m)) {
        $num = (int)$m[1];
        $title = trim($m[2]);
        $slug = $link['slug'];
        
        $cat = $curated[$num]['cat'] ?? 'Architecture';
        $desc = $curated[$num]['desc'] ?? 'Explore this new module in the Masterclass Syllabus.';
        $numFormatted = str_pad((string)$num, 2, '0', STR_PAD_LEFT);
        
        $gridHtml[] = '                <!-- Chapter ' . $numFormatted . ' -->';
        $gridHtml[] = '                <a href="/syllabus#' . $slug . '" class="module-card">';
        $gridHtml[] = '                    <div class="module-card__header"><span class="module-card__num">Chapter ' . $numFormatted . '</span><span class="badge badge--neutral">' . htmlspecialchars($cat) . '</span></div>';
        $gridHtml[] = '                    <h3 class="module-card__title">' . htmlspecialchars($title) . '</h3>';
        $gridHtml[] = '                    <p class="module-card__desc">' . $desc . '</p>';
        $gridHtml[] = '                </a>';
    }
}

$gridString = implode("\n", $gridHtml);
$welcomeHtml = preg_replace(
    '/<!-- SYLLABUS_GRID_START -->.*?<!-- SYLLABUS_GRID_END -->/s',
    "<!-- SYLLABUS_GRID_START -->\n" . $gridString . "\n                <!-- SYLLABUS_GRID_END -->",
    $welcomeHtml
);

file_put_contents($welcomePath, $welcomeHtml);
echo "Welcome landing page grid regenerated successfully.\n";
