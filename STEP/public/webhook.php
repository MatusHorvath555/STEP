<?php

// webhook.php – pošle správu do Discordu cez webhook

function posliNaDiscord(string $sprava): void
{
    // URL je v súbore mimo gitu – repozitár je verejný a s URL by mohol do kanála písať ktokoľvek
    $subor = __DIR__ . '/../config/discord_webhook.txt';
    $url = is_file($subor) ? trim(file_get_contents($subor)) : '';
    if ($url === '') {
        return; // bez súboru appka funguje ďalej, len bez notifikácií
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'content' => $sprava,
            'allowed_mentions' => ['parse' => []], // "@everyone" v názve úlohy nikoho neoznačí
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5, // keď Discord nereaguje, nástenka nezamrzne
    ]);

    $odpoved = curl_exec($ch);
    $kod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($odpoved === false || $kod >= 300) {
        error_log("Discord webhook zlyhal ($kod): " . curl_error($ch) . ' ' . $odpoved);
    }
    curl_close($ch);
}

// Volá sa po vytvorení úlohy (create_task.php, team_leader_dashboard.php)
function oznamNovuUlohu(string $nazov): void
{
    posliNaDiscord('🆕 ' . $_SESSION['name'] . ' vytvoril(a) novú úlohu: **' . $nazov . '**');
}

// Volá sa po zmene stavu na "Done" (Kanban, detail úlohy, moje úlohy, dashboard vedúceho)
function oznamHotovuUlohu(string $nazov): void
{
    posliNaDiscord('✅ ' . $_SESSION['name'] . ' dokončil(a) úlohu: **' . $nazov . '**');
}
