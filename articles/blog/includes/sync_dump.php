<?php
// Outils de dump SQL et de zip uploads pour la synchronisation.

const SYNC_TABLES = ['settings', 'users', 'articles', 'article_images', 'comments', 'social_links'];

function sync_build_sql_dump(PDO $pdo, string $outputFile): void {
    $fp = fopen($outputFile, 'w');
    if (!$fp) throw new RuntimeException("Impossible d'ecrire $outputFile");

    fwrite($fp, "-- Sync dump genere le " . date('c') . "\n");
    fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");

    foreach (SYNC_TABLES as $table) {
        fwrite($fp, "\n-- Table {$table}\n");
        fwrite($fp, "TRUNCATE TABLE `{$table}`;\n");

        $stmt = $pdo->query("SELECT * FROM `{$table}`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = '`' . implode('`,`', array_keys($row)) . '`';
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote((string)$v);
            }, array_values($row));
            fwrite($fp, "INSERT INTO `{$table}` ({$cols}) VALUES (" . implode(',', $vals) . ");\n");
        }
    }

    fwrite($fp, "\nSET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fp);
}

function sync_build_uploads_zip(string $uploadsDir, string $outputFile): int {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Extension ZipArchive requise.');
    }
    $zip = new ZipArchive();
    if ($zip->open($outputFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de creer le ZIP');
    }
    $count = 0;
    if (is_dir($uploadsDir)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $f) {
            if ($f->isFile()) {
                $rel = substr($f->getPathname(), strlen($uploadsDir) + 1);
                $rel = str_replace('\\', '/', $rel);
                if ($rel === '.gitkeep') continue;
                $zip->addFile($f->getPathname(), $rel);
                $count++;
            }
        }
    }
    $zip->close();
    return $count;
}

function sync_apply_sql_dump(PDO $pdo, string $sqlFile): void {
    $sql = file_get_contents($sqlFile);
    if ($sql === false) throw new RuntimeException('Impossible de lire le dump SQL');

    // Coupe sur ";\n" pour rester safe (les INSERT contiennent des ; dans les strings).
    $statements = preg_split("/;\s*\n/", $sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        if (preg_match('/^--/', $stmt)) continue;
        $pdo->exec($stmt);
    }
}

function sync_apply_payload(PDO $pdo, string $zipFile, string $uploadsDir, array $opts): array {
    // Applique un payload (ZIP contenant data.json + uploads/) selon les options :
    //   mode            : 'miroir' (remplace tout) | 'fusion' (ajoute sans ecraser)
    //                      | 'upsert' (ajoute OU met a jour par ID, sans toucher users/settings)
    //   include_db      : bool (traite ou non data.json)
    //   include_uploads : bool (traite ou non le dossier uploads/ du ZIP)
    $mode = $opts['mode'] ?? 'miroir';
    $mode = in_array($mode, ['fusion', 'miroir', 'upsert'], true) ? $mode : 'miroir';
    $includeDb      = !empty($opts['include_db']);
    $includeUploads = !empty($opts['include_uploads']);

    if (!class_exists('ZipArchive')) throw new RuntimeException('Extension ZipArchive requise.');
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) throw new RuntimeException('Payload ZIP illisible');

    $summary = ['mode' => $mode];

    try {
        if ($includeDb) {
            $jsonRaw = $zip->getFromName('data.json');
            if ($jsonRaw === false) throw new RuntimeException('payload.zip ne contient pas data.json');
            $data = json_decode($jsonRaw, true);
            if (!is_array($data)) throw new RuntimeException('data.json invalide');

            if ($mode === 'fusion') {
                $summary['db'] = sync_merge_json($pdo, $data);
            } elseif ($mode === 'upsert') {
                $summary['db'] = sync_upsert_json($pdo, $data);
            } else {
                $summary['db'] = sync_import_json($pdo, $data);
            }
        }

        if ($includeUploads) {
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

            if ($mode === 'miroir') {
                $iter = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iter as $f) {
                    if ($f->getFilename() === '.gitkeep') continue;
                    if ($f->isDir()) { @rmdir($f->getPathname()); }
                    else { @unlink($f->getPathname()); }
                }
            }

            $added = 0;
            $skipped = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false) continue;
                $entry = str_replace('\\', '/', $entry);
                if (strpos($entry, 'uploads/') !== 0) continue;
                if (substr($entry, -1) === '/') continue;
                $rel = substr($entry, strlen('uploads/'));
                if ($rel === '' || $rel === '.gitkeep') continue;
                if (strpos($rel, '..') !== false) continue;

                $dest = $uploadsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if ($mode === 'fusion' && file_exists($dest)) { $skipped++; continue; }

                $destDir = dirname($dest);
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                $stream = $zip->getStream($entry);
                if ($stream) {
                    $out = fopen($dest, 'wb');
                    if ($out) {
                        stream_copy_to_stream($stream, $out);
                        fclose($out);
                        $added++;
                    }
                    fclose($stream);
                }
            }
            $summary['uploads'] = ['added' => $added, 'skipped' => $skipped];
        }
    } finally {
        $zip->close();
    }

    return $summary;
}

function sync_apply_uploads_zip_merge(string $zipFile, string $uploadsDir): int {
    // Fusion : extrait uniquement les fichiers du ZIP dont le nom n'existe pas
    // deja dans uploads/. Pas de purge.
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) throw new RuntimeException('ZIP illisible');

    $count = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if ($entry === false) continue;
        $entry = str_replace('\\', '/', $entry);
        if (substr($entry, -1) === '/') continue;
        if ($entry === '.gitkeep') continue;
        if (strpos($entry, '..') !== false) continue;

        $dest = $uploadsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
        if (file_exists($dest)) continue;

        $destDir = dirname($dest);
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $stream = $zip->getStream($entry);
        if ($stream) {
            $out = fopen($dest, 'wb');
            if ($out) {
                stream_copy_to_stream($stream, $out);
                fclose($out);
                $count++;
            }
            fclose($stream);
        }
    }
    $zip->close();
    return $count;
}

function sync_apply_uploads_zip(string $zipFile, string $uploadsDir): int {
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    // Vide le dossier (sauf .gitkeep)
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $f) {
        if ($f->getFilename() === '.gitkeep') continue;
        if ($f->isDir()) { @rmdir($f->getPathname()); }
        else { @unlink($f->getPathname()); }
    }

    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) throw new RuntimeException('ZIP illisible');
    $count = $zip->numFiles;
    $zip->extractTo($uploadsDir);
    $zip->close();
    return $count;
}

function sync_export_json(PDO $pdo): array {
    $out = ['_meta' => ['exported_at' => date('c'), 'version' => 1]];
    foreach (SYNC_TABLES as $table) {
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        $out[$table] = $rows;
    }
    return $out;
}

function sync_build_payload(PDO $pdo, string $uploadsDir, string $outputFile, bool $includeDb, bool $includeUploads, ?callable $progress = null): array {
    // Construit le ZIP d'envoi en ne mettant QUE ce qui est demande.
    // $progress($message) (optionnel) est appele a chaque micro-etape pour un suivi live.
    $emit = $progress ?: function () {};

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Extension ZipArchive requise.');
    }
    $zip = new ZipArchive();
    if ($zip->open($outputFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de creer le ZIP');
    }

    if ($includeDb) {
        $emit('Export de la base de données…');
        $out = ['_meta' => ['exported_at' => date('c'), 'version' => 1]];
        foreach (SYNC_TABLES as $table) {
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            $out[$table] = $rows;
            $emit('  • table ' . $table . ' : ' . count($rows) . ' ligne(s)');
        }
        $zip->addFromString(
            'data.json',
            json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $emit('data.json ajouté au ZIP.');
    }

    $count = 0;
    if ($includeUploads && is_dir($uploadsDir)) {
        // On liste d'abord pour connaître le total.
        $files = [];
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $f) {
            if ($f->isFile()) {
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($uploadsDir) + 1));
                if ($rel === '.gitkeep') continue;
                $files[] = [$f->getPathname(), $rel];
            }
        }
        $total = count($files);
        $emit("Ajout des images au ZIP : {$total} fichier(s)…");
        foreach ($files as $i => $pair) {
            $zip->addFile($pair[0], 'uploads/' . $pair[1]);
            $count++;
            // On n'émet pas une ligne par fichier si énorme : on échantillonne.
            if ($total <= 25 || ($i % 10) === 0 || $i === $total - 1) {
                $emit('  • image ' . ($i + 1) . '/' . $total . ' : ' . $pair[1]);
            }
        }
    }

    $emit('Compression / finalisation du ZIP…');
    $zip->close();
    return ['db' => $includeDb, 'uploads' => $count];
}

function sync_build_full_export(PDO $pdo, string $uploadsDir, string $outputFile): int {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Extension ZipArchive requise.');
    }
    $zip = new ZipArchive();
    if ($zip->open($outputFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de creer le ZIP');
    }

    $jsonData = sync_export_json($pdo);
    $zip->addFromString(
        'data.json',
        json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    $count = 0;
    if (is_dir($uploadsDir)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $f) {
            if ($f->isFile()) {
                $rel = substr($f->getPathname(), strlen($uploadsDir) + 1);
                $rel = str_replace('\\', '/', $rel);
                if ($rel === '.gitkeep') continue;
                $zip->addFile($f->getPathname(), 'uploads/' . $rel);
                $count++;
            }
        }
    }
    $zip->close();
    return $count;
}

function sync_merge_json(PDO $pdo, array $data): array {
    // Mode FUSION : ajoute les lignes du JSON dont l'ID n'existe pas encore en BDD.
    // En cas de conflit de PRIMARY KEY ou de UNIQUE (id, email, platform, key...), MySQL
    // ignore silencieusement via INSERT IGNORE. Aucun ecrasement, aucun doublon.
    $result = [];

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->beginTransaction();

    try {
        foreach (SYNC_TABLES as $table) {
            if (!isset($data[$table]) || !is_array($data[$table])) continue;

            $added   = 0;
            $skipped = 0;

            foreach ($data[$table] as $row) {
                if (!is_array($row) || empty($row)) continue;

                $cols = '`' . implode('`,`', array_keys($row)) . '`';
                $placeholders = implode(',', array_fill(0, count($row), '?'));
                $stmt = $pdo->prepare("INSERT IGNORE INTO `{$table}` ({$cols}) VALUES ({$placeholders})");
                $stmt->execute(array_values($row));

                if ($stmt->rowCount() === 1) $added++;
                else                         $skipped++;
            }

            $result[$table] = ['added' => $added, 'skipped' => $skipped];
        }

        $pdo->commit();
    } catch (Throwable $e) {
        try { $pdo->rollBack(); } catch (Throwable $_) {}
        try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $_) {}
        throw new RuntimeException("Fusion annulee, BDD inchangee : " . $e->getMessage());
    }

    try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $_) {}
    return $result;
}

function sync_apply_full_import(PDO $pdo, string $zipFile, string $uploadsDir): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Extension ZipArchive requise.');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        throw new RuntimeException('ZIP illisible');
    }

    $jsonRaw = $zip->getFromName('data.json');
    if ($jsonRaw === false) {
        $zip->close();
        throw new RuntimeException('Le ZIP ne contient pas data.json');
    }
    $data = json_decode($jsonRaw, true);
    if (!is_array($data)) {
        $zip->close();
        throw new RuntimeException('data.json invalide');
    }

    $imported = sync_import_json($pdo, $data);

    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $f) {
        if ($f->getFilename() === '.gitkeep') continue;
        if ($f->isDir()) { @rmdir($f->getPathname()); }
        else { @unlink($f->getPathname()); }
    }

    $uploadsCount = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if ($entry === false) continue;
        $entry = str_replace('\\', '/', $entry);
        if (strpos($entry, 'uploads/') !== 0) continue;
        if (substr($entry, -1) === '/') continue;
        $rel = substr($entry, strlen('uploads/'));
        if ($rel === '' || $rel === '.gitkeep') continue;
        if (strpos($rel, '..') !== false) continue;
        $dest = $uploadsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $destDir = dirname($dest);
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $stream = $zip->getStream($entry);
        if ($stream) {
            $out = fopen($dest, 'wb');
            if ($out) {
                stream_copy_to_stream($stream, $out);
                fclose($out);
                $uploadsCount++;
            }
            fclose($stream);
        }
    }
    $zip->close();

    $imported['_uploads_files'] = $uploadsCount;
    return $imported;
}

function sync_upsert_json(PDO $pdo, array $data): array {
    // Mode UPSERT (mise a jour) :
    //   - settings / users : INSERT IGNORE (on n'ecrase jamais comptes ni reglages du serveur)
    //   - autres tables     : INSERT ... ON DUPLICATE KEY UPDATE (ajoute OU met a jour par ID)
    // Ainsi un article modifie en local est bien REPERCUTE sur le serveur (meme ID -> update).
    $noOverwrite = ['settings', 'users'];
    $result = [];

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->beginTransaction();
    try {
        foreach (SYNC_TABLES as $table) {
            if (!isset($data[$table]) || !is_array($data[$table])) continue;

            $added = 0; $updated = 0; $skipped = 0;
            foreach ($data[$table] as $row) {
                if (!is_array($row) || empty($row)) continue;
                $cols   = array_keys($row);
                $colSql = '`' . implode('`,`', $cols) . '`';
                $ph     = implode(',', array_fill(0, count($cols), '?'));

                if (in_array($table, $noOverwrite, true)) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO `{$table}` ({$colSql}) VALUES ({$ph})");
                    $stmt->execute(array_values($row));
                    if ($stmt->rowCount() === 1) $added++; else $skipped++;
                } else {
                    $set = [];
                    foreach ($cols as $c) {
                        if ($c === 'id') continue; // on ne met pas a jour la cle primaire
                        $set[] = "`{$c}`=VALUES(`{$c}`)";
                    }
                    $onDup = $set ? (' ON DUPLICATE KEY UPDATE ' . implode(',', $set)) : '';
                    $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$colSql}) VALUES ({$ph}){$onDup}");
                    $stmt->execute(array_values($row));
                    $rc = $stmt->rowCount(); // MySQL : 1 = inseree, 2 = mise a jour, 0 = identique
                    if ($rc === 1) $added++; elseif ($rc >= 2) $updated++; else $skipped++;
                }
            }
            $result[$table] = ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
        }
        $pdo->commit();
    } catch (Throwable $e) {
        try { $pdo->rollBack(); } catch (Throwable $_) {}
        try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $_) {}
        throw new RuntimeException("Mise a jour annulee, BDD inchangee : " . $e->getMessage());
    }
    try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $_) {}
    return $result;
}

function sync_import_json(PDO $pdo, array $data): array {
    // Import transactionnel : DELETE + INSERT dans une transaction, rollback complet
    // sur la moindre erreur. On n'utilise PAS TRUNCATE (DDL = commit implicite,
    // empecherait le rollBack). DELETE est plus lent mais reversible.
    $imported = [];
    $failingTable = null;

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->beginTransaction();

    try {
        foreach (SYNC_TABLES as $table) {
            if (!isset($data[$table]) || !is_array($data[$table])) continue;
            $failingTable = $table;

            $pdo->exec("DELETE FROM `{$table}`");
            $count = 0;
            foreach ($data[$table] as $row) {
                if (!is_array($row)) continue;
                $cols = '`' . implode('`,`', array_keys($row)) . '`';
                $placeholders = implode(',', array_fill(0, count($row), '?'));
                $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$cols}) VALUES ({$placeholders})");
                $stmt->execute(array_values($row));
                $count++;
            }
            $imported[$table] = $count;
            $failingTable = null;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        try { $pdo->rollBack(); } catch (Throwable $_) {}
        try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $_) {}
        $where = $failingTable ? " (table `{$failingTable}`)" : '';
        throw new RuntimeException("Import annule, BDD inchangee{$where} : " . $e->getMessage());
    }

    try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $_) {}
    return $imported;
}
