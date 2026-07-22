"""
Crée un robot humanoïde inspiré d'Optimus (Tesla) et l'exporte en .glb pour Three.js.

    "C:\\Program Files\\Blender Foundation\\Blender 5.1\\blender.exe" --background --python scripts/creer-optimus.py

Le style repose sur trois choses : des coques blanches mates, des articulations noires
qui creusent la silhouette, et une visière noire brillante à la place d'un visage.
Le robot mesure ~1,8 unité, pieds posés à z = 0, comme un humain de 1,80 m.
"""

import bpy
import math
import os
from mathutils import Matrix, Vector

SORTIE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "models")


def vider_scene():
    bpy.ops.object.select_all(action="SELECT")
    bpy.ops.object.delete()
    for bloc in (bpy.data.meshes, bpy.data.materials):
        for item in bloc:
            bloc.remove(item)


def materiau(nom, couleur, metallic=0.0, roughness=0.5):
    """Seul le nœud « Principled BSDF » survit à l'export glTF."""
    mat = bpy.data.materials.new(nom)
    if not mat.node_tree:
        mat.use_nodes = True

    # Chercher le nœud par TYPE et non par nom : « Principled BSDF » est un libellé,
    # il change avec la langue de l'interface et n'existe pas dans un arbre vide.
    bsdf = next((n for n in mat.node_tree.nodes if n.type == "BSDF_PRINCIPLED"), None)
    if bsdf is None:
        bsdf = mat.node_tree.nodes.new("ShaderNodeBsdfPrincipled")
        sortie = next(n for n in mat.node_tree.nodes if n.type == "OUTPUT_MATERIAL")
        mat.node_tree.links.new(bsdf.outputs["BSDF"], sortie.inputs["Surface"])
    bsdf.inputs["Base Color"].default_value = (*couleur, 1.0)
    bsdf.inputs["Metallic"].default_value = metallic
    bsdf.inputs["Roughness"].default_value = roughness
    return mat


def selectionner(objet):
    bpy.ops.object.select_all(action="DESELECT")
    objet.select_set(True)
    bpy.context.view_layer.objects.active = objet


def lisser(objet):
    """
    Un lissage total transformerait un cube biseauté en patate.
    `shade_auto_smooth` ne lisse que les arêtes plus douces qu'un angle donné :
    les biseaux deviennent ronds, les faces plates restent plates.
    """
    selectionner(objet)
    if hasattr(bpy.ops.object, "shade_auto_smooth"):
        bpy.ops.object.shade_auto_smooth(angle=0.52)  # 30 degrés
    else:
        bpy.ops.object.shade_smooth()


def parenter(enfant, parent):
    """
    `enfant.parent = parent` applique la transformation du parent PAR-DESSUS celle de
    l'enfant, qui se retrouve déplacé deux fois. `matrix_parent_inverse` annule cet effet :
    c'est ce que fait Blender lors d'un Ctrl+P à la souris.
    """
    if parent:
        enfant.parent = parent
        enfant.matrix_parent_inverse = parent.matrix_world.inverted()


def pivoter(objet, articulation):
    """
    Déplace l'ORIGINE de l'objet sur son articulation, sans le bouger à l'écran.

    Un objet tourne toujours autour de son origine. Une cuisse dont l'origine est au
    centre pivoterait comme une hélice ; il lui faut son origine sur la hanche.
    On décale donc le maillage dans un sens, et l'objet dans l'autre : les deux
    déplacements s'annulent visuellement, seul le point de rotation a changé.
    """
    pivot = Vector(articulation)
    objet.data.transform(Matrix.Translation(objet.location - pivot))
    objet.location = pivot


def boite(nom, dims, centre, mat, bevel=0.02, parent=None):
    """Un pavé biseauté : la brique de base de toutes les coques."""
    bpy.ops.mesh.primitive_cube_add(size=1, location=centre)
    obj = bpy.context.object
    obj.name = nom
    obj.scale = dims

    # Sans appliquer l'échelle, le biseau serait large sur un axe et fin sur l'autre.
    selectionner(obj)
    bpy.ops.object.transform_apply(location=False, rotation=False, scale=True)

    if bevel:
        mod = obj.modifiers.new("Bevel", "BEVEL")
        mod.width = bevel
        mod.segments = 3
        mod.limit_method = "ANGLE"
        bpy.ops.object.modifier_apply(modifier=mod.name)

    lisser(obj)
    obj.data.materials.append(mat)
    parenter(obj, parent)
    return obj


def capsule(nom, rayon, hauteur, centre, mat, parent=None):
    """Un cylindre aux bouts arrondis : membres et articulations."""
    bpy.ops.mesh.primitive_cylinder_add(radius=rayon, depth=hauteur, location=centre, vertices=24)
    obj = bpy.context.object
    obj.name = nom

    selectionner(obj)
    mod = obj.modifiers.new("Bevel", "BEVEL")
    mod.width = min(rayon * 0.45, hauteur * 0.2)
    mod.segments = 4
    bpy.ops.object.modifier_apply(modifier=mod.name)

    lisser(obj)
    obj.data.materials.append(mat)
    parenter(obj, parent)
    return obj


def sphere(nom, rayon, centre, mat, aplatissement=(1, 1, 1), parent=None):
    bpy.ops.mesh.primitive_uv_sphere_add(radius=rayon, location=centre, segments=32, ring_count=16)
    obj = bpy.context.object
    obj.name = nom
    obj.scale = aplatissement
    selectionner(obj)
    bpy.ops.object.shade_smooth()   # une sphère se lisse entièrement, sans réserve
    obj.data.materials.append(mat)
    parenter(obj, parent)
    return obj


def creer_optimus():
    # Optimus n'est pas blanc pur : ses coques sont d'un gris très clair, légèrement froid,
    # et surtout SATINÉES (roughness ~0.4). Un blanc brillant ferait jouet en plastique.
    blanc = materiau("Coque_Claire", (0.80, 0.81, 0.83), metallic=0.10, roughness=0.42)

    # Le noir structurel est mat : c'est lui qui creuse les articulations et les membres.
    noir = materiau("Structure_Noire", (0.045, 0.045, 0.05), metallic=0.25, roughness=0.55)

    # La tête est une coque noire LAQUÉE, presque un miroir. Aucun œil, aucune bouche :
    # tout le « regard » vient de ce reflet. C'est la signature visuelle d'Optimus.
    laque = materiau("Tete_Laquee", (0.02, 0.02, 0.025), metallic=0.35, roughness=0.12)

    metal = materiau("Metal", (0.58, 0.59, 0.62), metallic=1.0, roughness=0.35)

    # ---------- Torse : la pièce maîtresse, tout le reste s'y accroche ----------
    # Le noyau noir est légèrement plus étroit que les coques : il creuse la silhouette.
    torse = boite("Torse_Noyau", (0.30, 0.19, 0.34), (0, 0, 1.28), noir, bevel=0.03)

    boite("Plastron", (0.375, 0.235, 0.27), (0, -0.008, 1.405), blanc, bevel=0.04, parent=torse)
    boite("Dos", (0.34, 0.10, 0.24), (0, 0.09, 1.38), blanc, bevel=0.03, parent=torse)
    boite("Abdomen", (0.215, 0.16, 0.13), (0, 0, 1.17), noir, bevel=0.025, parent=torse)
    boite("Bassin", (0.31, 0.19, 0.13), (0, 0, 1.07), blanc, bevel=0.03, parent=torse)

    # ---------- Tête ----------
    capsule("Cou", 0.052, 0.055, (0, 0.015, 1.575), noir, parent=torse)

    # Le crâne : un rectangle noir laqué aux angles adoucis, PAS une olive.
    # Un biseau trop large (0,06 sur 0,19) arrondit tout et efface la face avant plate,
    # qui est justement ce qui fait lire « casque » plutôt que « caillou ».
    tete = boite("Tete", (0.19, 0.205, 0.20), (0, 0.005, 1.695), laque, bevel=0.032, parent=torse)

    # Le seul détail : un mince liseré clair au sommet, encastré dans la coque.
    boite("Liseré", (0.125, 0.135, 0.006), (0, 0.02, 1.792), blanc, bevel=0.002, parent=tete)

    # ---------- Membres, en miroir ----------
    # Chaque segment animé reçoit son origine SUR son articulation (voir `pivoter`),
    # et devient le parent du segment suivant : plier le genou emporte le pied.
    membres = {}

    for cote, s in (("G", -1), ("D", 1)):
        # --- Bras : épaule → bras → coude → avant-bras → poignet → main ---
        sphere(f"Epaule_{cote}", 0.078, (s * 0.205, 0, 1.475), blanc, (1.0, 0.92, 0.85), parent=torse)

        bras = capsule(f"Bras_{cote}", 0.048, 0.30, (s * 0.215, 0, 1.30), noir)
        pivoter(bras, (s * 0.215, 0, 1.45))          # pivot = l'épaule
        parenter(bras, torse)

        sphere(f"Coude_{cote}", 0.05, (s * 0.215, 0, 1.14), metal, parent=bras)

        avant = capsule(f"AvantBras_{cote}", 0.046, 0.27, (s * 0.215, 0, 1.00), blanc)
        pivoter(avant, (s * 0.215, 0, 1.14))         # pivot = le coude
        parenter(avant, bras)

        sphere(f"Poignet_{cote}", 0.038, (s * 0.215, 0, 0.865), metal, parent=avant)

        # Main : une paume et quatre doigts. Assez pour lire « main » de loin.
        main = boite(f"Main_{cote}", (0.055, 0.085, 0.10), (s * 0.215, 0, 0.79), noir,
                     bevel=0.012, parent=avant)
        for i in range(4):
            boite(f"Doigt_{cote}{i}", (0.011, 0.02, 0.075),
                  (s * 0.215 + (i - 1.5) * 0.014, -0.028, 0.705), noir, bevel=0.004, parent=main)
        boite(f"Pouce_{cote}", (0.014, 0.055, 0.018), (s * 0.245, 0.005, 0.755), noir,
              bevel=0.005, parent=main)

        # --- Jambe : hanche → cuisse → genou → tibia → cheville → pied ---
        sphere(f"Hanche_{cote}", 0.065, (s * 0.095, 0, 1.02), metal, parent=torse)

        cuisse = boite(f"Cuisse_{cote}", (0.145, 0.155, 0.40), (s * 0.095, 0, 0.80), blanc, bevel=0.03)
        pivoter(cuisse, (s * 0.095, 0, 1.00))        # pivot = la hanche
        parenter(cuisse, torse)

        sphere(f"Genou_{cote}", 0.062, (s * 0.095, -0.005, 0.575), metal, parent=cuisse)

        tibia = boite(f"Tibia_{cote}", (0.115, 0.13, 0.40), (s * 0.095, 0, 0.34), blanc, bevel=0.028)
        pivoter(tibia, (s * 0.095, 0, 0.575))        # pivot = le genou
        parenter(tibia, cuisse)

        sphere(f"Cheville_{cote}", 0.052, (s * 0.095, 0, 0.115), metal, parent=tibia)

        # Le pied dépasse vers l'avant (Y négatif), et la cheville comble l'espace
        # entre le bas du tibia (0,14) et le dessus du pied (0,075).
        pied = boite(f"Pied_{cote}", (0.12, 0.27, 0.075), (s * 0.095, -0.045, 0.038), noir, bevel=0.02)
        pivoter(pied, (s * 0.095, 0, 0.115))         # pivot = la cheville
        parenter(pied, tibia)

        membres[cote] = {"bras": bras, "avant": avant, "cuisse": cuisse, "tibia": tibia, "pied": pied}

    return torse, membres


def courbes(action):
    """Depuis Blender 4.4, les courbes vivent dans des couches (« slotted actions »)."""
    if hasattr(action, "fcurves"):
        yield from action.fcurves
        return
    for couche in action.layers:
        for strip in couche.strips:
            for sac in strip.channelbags:
                yield from sac.fcurves


def lisser_courbes(objet):
    """Interpolation douce : sans elle, le mouvement est linéaire et saccadé."""
    if not objet.animation_data or not objet.animation_data.action:
        return
    for fcurve in courbes(objet.animation_data.action):
        for kp in fcurve.keyframe_points:
            kp.interpolation = "BEZIER"


def cle_rotation(objet, image, angle_deg):
    """Pose une clé de rotation autour de X (l'axe des hanches et des épaules)."""
    bpy.context.scene.frame_set(image)
    objet.rotation_euler.x = math.radians(angle_deg)
    objet.keyframe_insert(data_path="rotation_euler", index=0)


def animer_marche(torse, membres):
    """
    Un cycle de marche complet, sur 32 images (1,33 s à 24 i/s).

    Le robot regarde vers -Y. Une rotation POSITIVE autour de X ramène le bas d'un membre
    vers +Y, donc vers l'arrière ; une rotation négative le projette vers l'avant.

    Le cycle est décrit par quatre poses, et la jambe gauche reprend celles de la droite
    avec un demi-cycle de décalage — c'est ce déphasage qui fait la marche.
    """
    scene = bpy.context.scene
    scene.frame_start = 1
    scene.frame_end = 33  # l'image 33 rejoue l'image 1 : la boucle est invisible

    # phase → (cuisse, genou, cheville), en degrés autour de X.
    #
    # ANATOMIE : un genou ne se plie QUE vers l'arrière — le tibia part vers +Y, donc
    # l'angle du genou est toujours POSITIF. Avec des valeurs négatives, la jambe
    # s'hyperextendait vers l'avant et les deux pieds partaient devant le corps.
    JAMBE = {
        0:   (-24,   4,   6),   # contact : talon devant, jambe presque tendue
        25:  (-2,   32,   2),   # passage : le genou se plie pour ne pas racler le sol
        50:  (18,    6, -10),   # poussée : la jambe est derrière, le pied se déroule
        75:  (6,    48,   8),   # rappel : le talon remonte vers la fesse
    }
    # Les bras balancent à l'opposé de la jambe du même côté : c'est ce qui donne l'équilibre.
    BRAS = {0: 20, 25: 4, 50: -20, 75: -4}
    AVANT = {0: -16, 25: -22, 50: -6, 75: -14}

    images = {0: 1, 25: 9, 50: 17, 75: 25}   # 4 poses réparties sur les 32 images

    for cote, decalage in (("D", 0), ("G", 50)):
        m = membres[cote]

        for phase in (0, 25, 50, 75):
            source = (phase + decalage) % 100
            image = images[phase]

            cuisse, genou, cheville = JAMBE[source]
            cle_rotation(m["cuisse"], image, cuisse)
            cle_rotation(m["tibia"], image, genou)
            cle_rotation(m["pied"], image, cheville)

            cle_rotation(m["bras"], image, BRAS[source])
            cle_rotation(m["avant"], image, AVANT[source])

        # Reboucler : l'image 33 reprend exactement la pose de l'image 1.
        for os_, table, index in (
            (m["cuisse"], JAMBE, 0), (m["tibia"], JAMBE, 1), (m["pied"], JAMBE, 2),
        ):
            cle_rotation(os_, 33, table[decalage % 100][index])
        cle_rotation(m["bras"], 33, BRAS[decalage % 100])
        cle_rotation(m["avant"], 33, AVANT[decalage % 100])

        for os_ in m.values():
            lisser_courbes(os_)

    # Le bassin monte quand une jambe est tendue, descend au passage : deux oscillations
    # par cycle, puisque chaque jambe pousse à son tour.
    for image, z in {1: 1.28, 9: 1.255, 17: 1.28, 25: 1.255, 33: 1.28}.items():
        scene.frame_set(image)
        torse.location.z = z
        torse.keyframe_insert(data_path="location", index=2)
    lisser_courbes(torse)

    regrouper_en_clip("Marche")


def regrouper_en_clip(nom):
    """
    Sans NLA, l'exportateur glTF crée UNE animation par objet animé : on obtiendrait
    dix clips nommés « Cuisse_DAction ». En poussant chaque action dans une piste NLA
    portant le même nom, l'exportateur les fusionne en un seul clip, nommé `nom`.
    """
    for objet in bpy.context.scene.objects:
        anim = objet.animation_data
        if not anim or not anim.action:
            continue

        piste = anim.nla_tracks.new()
        piste.name = nom
        piste.strips.new(nom, int(anim.action.frame_range[0]), anim.action)
        anim.action = None


def exporter(nom_fichier):
    os.makedirs(SORTIE, exist_ok=True)
    chemin = os.path.join(SORTIE, nom_fichier)
    bpy.ops.export_scene.gltf(
        filepath=chemin,
        export_format="GLB",   # un seul fichier : maillage + matériaux + animation
        export_yup=True,       # Blender est Z-up, Three.js est Y-up
        export_apply=True,
        export_animations=True,

        # Sans ce mode, l'exportateur produit UNE animation par action, soit onze clips
        # « Cuisse_DAction », « Tibia_GAction »… inutilisables. En mode NLA_TRACKS, il
        # fusionne toutes les pistes portant le même nom en un seul clip : « Marche ».
        export_animation_mode="NLA_TRACKS",
    )
    print(f"\n>>> Exporté : {chemin} ({os.path.getsize(chemin)} octets)\n")


vider_scene()
torse, membres = creer_optimus()
animer_marche(torse, membres)
exporter("optimus.glb")
