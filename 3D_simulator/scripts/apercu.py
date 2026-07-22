"""
Rend une image d'un .glb pour pouvoir le REGARDER, au lieu de supposer qu'il est correct.

    blender.exe --background --python scripts/apercu.py -- optimus.glb

Sans argument, le robot par défaut est utilisé. L'image sort à côté du modèle, en .png.
"""

import bpy
import sys
import math
import os
from mathutils import Vector

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Blender passe ses propres arguments : ceux du script sont après le « -- ».
apres = sys.argv[sys.argv.index("--") + 1:] if "--" in sys.argv else []
NOM = apres[0] if apres else "optimus.glb"
IMAGE_ANIM = int(apres[1]) if len(apres) > 1 else 1   # quelle image du cycle rendre

MODELE = os.path.join(RACINE, "models", NOM)
IMAGE = os.path.join(RACINE, "models", f"{os.path.splitext(NOM)[0]}-apercu-{IMAGE_ANIM:02d}.png")

bpy.ops.object.select_all(action="SELECT")
bpy.ops.object.delete()

bpy.ops.import_scene.gltf(filepath=MODELE)

# Boîte englobante réelle de la scène, en coordonnées monde : elle permet de cadrer
# la caméra quelle que soit la taille du modèle importé.
mini = [1e9] * 3
maxi = [-1e9] * 3
for obj in bpy.context.scene.objects:
    if obj.type != "MESH":
        continue
    for coin in obj.bound_box:
        monde = obj.matrix_world @ Vector(coin)
        for i in range(3):
            mini[i] = min(mini[i], monde[i])
            maxi[i] = max(maxi[i], monde[i])

centre = [(mini[i] + maxi[i]) / 2 for i in range(3)]
taille = max(maxi[i] - mini[i] for i in range(3))

bpy.ops.mesh.primitive_plane_add(size=taille * 20, location=(0, 0, mini[2]))

# Caméra en trois-quarts face, légèrement au-dessus du centre du modèle.
d = taille * 1.9
bpy.ops.object.camera_add(
    location=(centre[0] + d * 1.0, centre[1] - d * 0.25, centre[2] + taille * 0.1),
    rotation=(math.radians(88), 0, math.radians(76)),
)
camera = bpy.context.object
camera.data.lens = 60
bpy.context.scene.camera = camera

bpy.ops.object.light_add(type="AREA", location=(centre[0] + d, centre[1] - d, centre[2] + taille * 1.4))
key = bpy.context.object
key.data.energy = 500 * taille * taille
key.data.size = taille * 2

bpy.ops.object.light_add(type="AREA", location=(centre[0] - d, centre[1] - d * 0.4, centre[2] + taille * 0.4))
fill = bpy.context.object
fill.data.energy = 120 * taille * taille
fill.data.size = taille * 3

scene = bpy.context.scene
scene.render.engine = "BLENDER_EEVEE"
scene.render.resolution_x = 640
scene.render.resolution_y = 800
scene.render.filepath = IMAGE
scene.frame_set(IMAGE_ANIM)

bpy.ops.render.render(write_still=True)
print(f"\n>>> Aperçu : {IMAGE}\n")
