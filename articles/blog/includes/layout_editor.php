<?php
// Fragment partagé : éditeur d'agencement (drag-and-drop).
// Attend $currentLayout (array de clés dans l'ordre actuel).
$labels = [
    'title'   => 'Titre',
    'cover'   => 'Couverture',
    'content' => 'Contenu',
    'gallery' => 'Galerie',
    'sources' => 'Sources',
];
?>
<fieldset class="layout-fieldset">
    <legend>Agencement</legend>
    <p class="muted">Glisse-dépose les blocs pour changer leur ordre. L'aperçu à droite se met à jour en temps réel. Les blocs vides (ex : pas de galerie) sont masqués à l'affichage.</p>
    <div class="layout-list" id="layout-list">
        <?php foreach ($currentLayout as $i => $key): ?>
            <div class="layout-item" data-block="<?= e($key) ?>" draggable="true">
                <span class="layout-grip" aria-hidden="true">⋮⋮</span>
                <span class="layout-pos-num"><?= $i + 1 ?></span>
                <span class="layout-label"><?= e($labels[$key]) ?></span>
                <input type="hidden" name="layout_pos[<?= e($key) ?>]" value="<?= $i + 1 ?>">
            </div>
        <?php endforeach; ?>
    </div>
</fieldset>
