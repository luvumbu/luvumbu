"""
Crée un petit robot dans Blender et l'exporte en .glb pour Three.js.

Lancement (sans ouvrir l'interface) :
    "C:\\Program Files\\Blender Foundation\\Blender 5.1\\blender.exe" --background --python scripts/creer-robot.py

Tout ce que fait ce script est faisable à la souris dans Blender. L'écrire en Python
le rend reproductible : on peut changer une couleur et régénérer le fichier en une commande.
"""

import bpy
import math
import os

SORTIE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "models")


def vider_scene():
    """Un fichier Blender neuf contient déjà un cube, une caméra et une lampe."""
    bpy.ops.object.select_all(action="SELECT")
    bpy.ops.object.delete()

    # Les données (maillages, matériaux) survivent à la suppression des objets.
    for bloc in (bpy.data.meshes, bpy.data.materials):
        for item in bloc:
            bloc.remove(item)


def materiau(nom, couleur, metallic=0.0, roughness=0.5):
    """
    Seul le nœud « Principled BSDF » est exporté vers glTF.
    Tout shader construit avec d'autres nœuds serait perdu : il faudrait le cuire en texture.
    """
    mat = bpy.data.materials.new(nom)
    if not mat.node_tree:  # avant Blender 5, l'arbre de nœuds n'existe pas par défaut
        mat.use_nodes = True
    bsdf = mat.node_tree.nodes["Principled BSDF"]
    bsdf.inputs["Base Color"].default_value = (*couleur, 1.0)
    bsdf.inputs["Metallic"].default_value = metallic
    bsdf.inputs["Roughness"].default_value = roughness
    return mat


def ajouter(objet, mat, parent=None):
    """
    Attention au piège : `objet.parent = parent` applique la transformation du parent
    PAR-DESSUS celle de l'enfant, qui se retrouve déplacé et déformé une seconde fois.
    `matrix_parent_inverse` annule exactement cet effet — c'est ce que fait Blender
    quand on parente à la souris avec Ctrl+P.
    """
    objet.data.materials.append(mat)
    if parent:
        objet.parent = parent
        objet.matrix_parent_inverse = parent.matrix_world.inverted()
    return objet


def lisser(objet):
    bpy.ops.object.select_all(action="DESELECT")
    objet.select_set(True)
    bpy.context.view_layer.objects.active = objet
    bpy.ops.object.shade_smooth()


def creer_robot():
    bleu = materiau("Bleu", (0.15, 0.45, 0.85), metallic=0.1, roughness=0.4)
    orange = materiau("Orange", (0.95, 0.42, 0.25), metallic=0.0, roughness=0.6)
    metal = materiau("Metal", (0.75, 0.78, 0.82), metallic=1.0, roughness=0.25)
    noir = materiau("Noir", (0.05, 0.05, 0.06), metallic=0.0, roughness=0.3)

    # Le robot est debout, pieds au sol (z = 0), et mesure environ 2,4 unités.
    # Chaque hauteur est calculée à partir de la précédente pour que les pièces se touchent.

    # --- Corps : un cube étiré, aux arêtes adoucies ---
    # Dimensions 0,62 × 0,44 × 0,80 → demi-hauteur 0,40. Les jambes montent à 0,55.
    bpy.ops.mesh.primitive_cube_add(size=1, location=(0, 0, 0.95))
    corps = bpy.context.object
    corps.name = "Corps"
    corps.scale = (0.62, 0.44, 0.80)

    # Sans appliquer l'échelle, le Bevel serait déformé (large sur X, fin sur Y)
    # et les enfants hériteraient de cette déformation.
    bpy.ops.object.transform_apply(location=False, rotation=False, scale=True)

    # Bevel adoucit les arêtes. Il faut l'APPLIQUER, sinon il n'existe pas dans le .glb.
    bevel = corps.modifiers.new("Bevel", "BEVEL")
    bevel.width = 0.05
    bevel.segments = 3
    bpy.ops.object.modifier_apply(modifier=bevel.name)
    lisser(corps)
    ajouter(corps, bleu)

    # --- Cou et tête ---  haut du corps : 0,95 + 0,40 = 1,35
    bpy.ops.mesh.primitive_cylinder_add(radius=0.10, depth=0.12, location=(0, 0, 1.40))
    cou = bpy.context.object
    cou.name = "Cou"
    ajouter(cou, metal, corps)

    bpy.ops.mesh.primitive_uv_sphere_add(radius=0.32, location=(0, 0, 1.72), segments=48, ring_count=24)
    tete = bpy.context.object
    tete.name = "Tete"
    tete.scale = (1.0, 0.92, 0.95)  # légèrement aplatie : plus vivante qu'une bille parfaite
    lisser(tete)
    ajouter(tete, bleu, corps)

    # --- Yeux --- posés sur la surface avant de la tête (Y négatif = vers l'observateur)
    for cote, x in (("G", -0.13), ("D", 0.13)):
        bpy.ops.mesh.primitive_uv_sphere_add(radius=0.07, location=(x, -0.26, 1.77))
        oeil = bpy.context.object
        oeil.name = f"Oeil_{cote}"
        lisser(oeil)
        ajouter(oeil, noir, tete)

    # --- Antenne ---  sommet de la tête : 1,72 + 0,32 × 0,95 ≈ 2,02
    bpy.ops.mesh.primitive_cylinder_add(radius=0.02, depth=0.28, location=(0, 0, 2.14))
    tige = bpy.context.object
    tige.name = "Antenne"
    ajouter(tige, metal, tete)

    bpy.ops.mesh.primitive_uv_sphere_add(radius=0.075, location=(0, 0, 2.32))
    boule = bpy.context.object
    boule.name = "Boule_Antenne"
    lisser(boule)
    ajouter(boule, orange, tige)

    # --- Bras ---  le corps est large de 0,62, donc son bord est à x = ±0,31
    for cote, x in (("G", -0.40), ("D", 0.40)):
        bpy.ops.mesh.primitive_cylinder_add(radius=0.07, depth=0.66, location=(x, 0, 1.05))
        bras = bpy.context.object
        bras.name = f"Bras_{cote}"
        lisser(bras)
        ajouter(bras, metal, corps)

        # Main au bout du bras : 1,05 − 0,33 = 0,72
        bpy.ops.mesh.primitive_uv_sphere_add(radius=0.11, location=(x, 0, 0.70))
        main = bpy.context.object
        main.name = f"Main_{cote}"
        lisser(main)
        ajouter(main, orange, bras)

    # --- Jambes et pieds ---  du sol (0,10) au bas du corps (0,55)
    for cote, x in (("G", -0.18), ("D", 0.18)):
        bpy.ops.mesh.primitive_cylinder_add(radius=0.09, depth=0.45, location=(x, 0, 0.33))
        jambe = bpy.context.object
        jambe.name = f"Jambe_{cote}"
        lisser(jambe)
        ajouter(jambe, metal, corps)

        bpy.ops.mesh.primitive_uv_sphere_add(radius=0.14, location=(x, -0.04, 0.10))
        pied = bpy.context.object
        pied.name = f"Pied_{cote}"
        pied.scale = (1.0, 1.35, 0.55)  # une sphère aplatie fait une chaussure convaincante
        lisser(pied)
        ajouter(pied, orange, jambe)

    return corps


def courbes(action):
    """
    Depuis Blender 4.4, les courbes d'animation vivent dans des couches (« slotted actions »)
    au lieu d'être posées à plat sur l'action. On gère les deux formes.
    """
    if hasattr(action, "fcurves"):
        yield from action.fcurves
        return

    for couche in action.layers:
        for strip in couche.strips:
            for sac in strip.channelbags:
                yield from sac.fcurves


def animer(corps):
    """
    Une « action » Blender devient un clip d'animation glTF, rejouable par un AnimationMixer.
    Ici : le robot flotte de haut en bas, en boucle sur 2 secondes (48 images à 24 fps).
    """
    scene = bpy.context.scene
    scene.frame_start = 1
    scene.frame_end = 48

    # Le corps monte puis redescend à sa hauteur de départ (0,95) : les pieds décollent
    # légèrement, mais ne s'enfoncent jamais sous le sol.
    hauteurs = {1: 0.95, 24: 1.03, 48: 0.95}
    for image, z in hauteurs.items():
        scene.frame_set(image)
        corps.location.z = z
        corps.keyframe_insert(data_path="location", index=2)

    action = corps.animation_data.action
    action.name = "Flottement"

    # Interpolation douce : sans ça, le mouvement est linéaire et saccadé.
    for fcurve in courbes(action):
        for kp in fcurve.keyframe_points:
            kp.interpolation = "BEZIER"


def exporter():
    os.makedirs(SORTIE, exist_ok=True)
    chemin = os.path.join(SORTIE, "robot.glb")

    bpy.ops.export_scene.gltf(
        filepath=chemin,
        export_format="GLB",       # un fichier unique : maillage + matériaux + animations
        export_yup=True,           # Blender est Z-up, Three.js est Y-up
        export_apply=True,         # applique les modificateurs restants
        export_animations=True,
    )
    print(f"\n>>> Exporté : {chemin} ({os.path.getsize(chemin)} octets)\n")


vider_scene()
corps = creer_robot()
animer(corps)
exporter()
