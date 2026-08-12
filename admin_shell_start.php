<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Admin Dashboard';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> - <?= h(APP_NAME) ?></title>
    <?php if (isset($extraHeadHtml) && $extraHeadHtml !== ''): ?>
        <?= $extraHeadHtml ?>
    <?php endif; ?>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body data-admin-page="<?= h($activePage) ?>">
    <main class="page">
        <header class="topbar">
            <div class="brand-lockup">
                <img class="brand-mark" src="assets/app-icon.svg" alt="" aria-hidden="true">
                <div>
                    <div class="brand-kicker">Ministry of Youth & Sports</div>
                    <div class="brand"><?= h(APP_NAME) ?></div>
                </div>
            </div>
            <nav class="nav-links" aria-label="Main navigation">
                <a href="index.php">Clock Screen</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <div class="admin-workspace">
            <aside class="admin-sidebar" aria-label="Admin pages">
                <div class="admin-sidebar-title">
                    <span class="eyebrow">Admin Pages</span>
                    <h2>Workspace</h2>
                </div>

                <nav class="admin-sidebar-nav">
                    <?php foreach ($adminPageLinks as $pageKey => $pageLink): ?>
                        <a class="sidebar-button <?= $activePage === $pageKey ? 'active' : '' ?>" href="<?= h($pageLink['href']) ?>">
                            <?= h($pageLink['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>

            <section class="content admin-dashboard">
