#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/app/core/config.php';

function connectDb(): PDO
{
    $dsn = DBDRIVER . ':host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4';

    return new PDO($dsn, DBUSER, DBPASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function parseMenuPlan(string $markdownPath): array
{
    if (!is_file($markdownPath))
    {
        throw new RuntimeException("Menu plan not found: {$markdownPath}");
    }

    $lines = file($markdownPath, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines))
    {
        throw new RuntimeException("Unable to read menu plan: {$markdownPath}");
    }

    $items = [];
    $currentCategory = '';
    $pendingItemIndex = null;

    foreach ($lines as $lineRaw)
    {
        $line = trim($lineRaw);
        if ($line === '')
        {
            continue;
        }

        if (preg_match('/^###\s+(.+?)\s*\((.+)\)\s*$/u', $line, $match) === 1)
        {
            $categoryZh = trim($match[1]);
            $categoryEn = trim($match[2]);
            $currentCategory = buildBilingualLabel($categoryZh, $categoryEn);
            $pendingItemIndex = null;
            continue;
        }

        if (preg_match('/^!\[\[(.+)\]\]$/u', $line, $match) === 1)
        {
            if ($pendingItemIndex === null)
            {
                continue;
            }

            $items[$pendingItemIndex]['source_image'] = trim($match[1]);
            $pendingItemIndex = null;
            continue;
        }

        if (preg_match('/^(.+?)\s*\((.+)\)\s*$/u', $line, $match) === 1)
        {
            $nameZh = trim($match[1]);
            $nameEn = trim($match[2]);
            $items[] = [
                'name' => buildBilingualLabel($nameZh, $nameEn),
                'description' => $nameZh !== '' ? $nameZh : null,
                'category' => $currentCategory,
                'source_image' => null,
            ];
            $pendingItemIndex = count($items) - 1;
        }
    }

    return $items;
}

function buildBilingualLabel(string $primary, string $secondary): string
{
    $primary = trim($primary);
    $secondary = trim($secondary);

    if ($primary === '')
    {
        return $secondary;
    }

    if ($secondary === '')
    {
        return $primary;
    }

    return $primary . ' (' . $secondary . ')';
}

function ensureDirectory(string $path): void
{
    if (is_dir($path))
    {
        @chmod($path, 0777);
        return;
    }

    if (!mkdir($path, 0777, true) && !is_dir($path))
    {
        throw new RuntimeException("Unable to create directory: {$path}");
    }

    @chmod($path, 0777);
}

function copySeedImage(string $sourcePath, string $targetDir, int $index): string
{
    if (!is_file($sourcePath))
    {
        throw new RuntimeException("Image file not found: {$sourcePath}");
    }

    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowed, true))
    {
        throw new RuntimeException("Unsupported image extension '{$extension}' for {$sourcePath}");
    }

    $filename = sprintf('menu-item-%03d.%s', $index + 1, $extension);
    $targetPath = rtrim($targetDir, '/') . '/' . $filename;

    if (!copy($sourcePath, $targetPath))
    {
        throw new RuntimeException("Failed to copy image to {$targetPath}");
    }

    return '/uploads/menu/' . $filename;
}

$root = dirname(__DIR__);
$menuPlanPath = $root . '/menuplan/Restaurant.md';
$menuPlanImageDir = $root . '/menuplan';
$uploadDir = $root . '/public/uploads/menu';

$items = parseMenuPlan($menuPlanPath);
if (empty($items))
{
    fwrite(STDOUT, "No menu items found in {$menuPlanPath}\n");
    exit(0);
}

ensureDirectory($uploadDir);

$pdo = connectDb();

$insert = $pdo->prepare(
    'INSERT INTO menu_items (name, description, price_pence, category, image_path, display_order, is_available) '
    . 'VALUES (:name, :description, :price_pence, :category, :image_path, :display_order, :is_available)'
);

$update = $pdo->prepare(
    'UPDATE menu_items '
    . 'SET description = :description, price_pence = :price_pence, category = :category, image_path = :image_path, display_order = :display_order, is_available = :is_available '
    . 'WHERE id = :id'
);

$findByOrder = $pdo->prepare('SELECT id FROM menu_items WHERE display_order = :display_order LIMIT 1');
$rename = $pdo->prepare('UPDATE menu_items SET name = :name WHERE id = :id');

$created = 0;
$updated = 0;

foreach ($items as $index => $item)
{
    $sourceImage = $item['source_image'] ? ($menuPlanImageDir . '/' . $item['source_image']) : null;
    $imagePath = $sourceImage ? copySeedImage($sourceImage, $uploadDir, $index) : null;

    $payload = [
        'name' => $item['name'],
        'description' => $item['description'] ?? null,
        'price_pence' => 0,
        'category' => $item['category'] !== '' ? $item['category'] : null,
        'image_path' => $imagePath,
        'display_order' => $index,
        'is_available' => 1,
    ];

    $findByOrder->execute(['display_order' => $index]);
    $row = $findByOrder->fetch();

    if ($row)
    {
        $update->execute([
            'id' => $row['id'],
            'description' => $payload['description'],
            'price_pence' => $payload['price_pence'],
            'category' => $payload['category'],
            'image_path' => $payload['image_path'],
            'display_order' => $payload['display_order'],
            'is_available' => $payload['is_available'],
        ]);

        $rename->execute([
            'id' => $row['id'],
            'name' => $payload['name'],
        ]);
        $updated++;
        continue;
    }

    $insert->execute($payload);
    $created++;
}

fwrite(STDOUT, "Menu seed complete. Created: {$created}, Updated: {$updated}\n");
