/**
 * Draw - Shape (classe de base)
 *
 * Classe abstraite dont heritent toutes les formes du canvas.
 * Gere les proprietes communes : couleur, epaisseur, opacite,
 * transformation (rotation, deformation), groupId, serialisation.
 *
 * Dependances : aucune
 */
class Shape {

  /**
   * @param {string} type - Identifiant du type de forme (circle, rect, line, etc.)
   */
  constructor(type) {
    this.type = type;         // Identifiant du type
    this.name = '';            // Nom affiche dans le panneau calques
    this.strokeColor = '#00d4ff'; // Couleur du contour
    this.fillColor = '#ffffff';   // Couleur de remplissage
    this.useFill = false;         // Remplissage actif ou non
    this.lineWidth = 2;           // Epaisseur du trait
    this.opacity = 1;             // Opacite (0 a 1)
    this.groupId = null;          // ID du groupe parent (null = pas de groupe)
    this.rotation = 0;            // Rotation en radians
    this.skewX = 0;               // Deformation horizontale
    this.skewY = 0;               // Deformation verticale
    this.dashStyle = 'solid';     // Style de trait : solid, dashed, dotted
    this.locked = false;          // Verrouille (pas de deplacement/modification)
    this.visible = true;          // Visible sur le canvas
    this.shadowColor = '';        // Ombre portee (vide = pas d'ombre)
    this.shadowBlur = 8;
    this.shadowOffsetX = 4;
    this.shadowOffsetY = 4;
    this.gradientType = 'none';  // none, linear, radial
    this.gradientColor1 = '#00d4ff';
    this.gradientColor2 = '#ff6b35';
    this.fontFamily = 'Segoe UI';
    this.fontBold = false;
    this.fontItalic = false;
  }

  /* --- Methodes a surcharger dans les sous-classes --- */

  /** Dessine la forme sur le contexte canvas */
  draw(ctx, highlight = false, ghost = false) {}

  /** Retourne une copie independante de la forme */
  clone() { return null; }

  /** Copie le style (couleurs, epaisseur, etc.) depuis une autre forme */
  copyStyle(src) {
    this.name = src.name ? src.name + ' copie' : '';
    this.strokeColor = src.strokeColor;
    this.fillColor = src.fillColor;
    this.useFill = src.useFill;
    this.lineWidth = src.lineWidth;
    this.opacity = src.opacity;
    this.groupId = src.groupId;
    this.rotation = src.rotation || 0;
    this.skewX = src.skewX || 0;
    this.skewY = src.skewY || 0;
    this.dashStyle = src.dashStyle || 'solid';
    this.locked = false;
    this.visible = src.visible !== undefined ? src.visible : true;
    this.shadowColor = src.shadowColor || '';
    this.shadowBlur = src.shadowBlur || 8;
    this.shadowOffsetX = src.shadowOffsetX || 4;
    this.shadowOffsetY = src.shadowOffsetY || 4;
    this.gradientType = src.gradientType || 'none';
    this.gradientColor1 = src.gradientColor1 || '#00d4ff';
    this.gradientColor2 = src.gradientColor2 || '#ff6b35';
    this.fontFamily = src.fontFamily || 'Segoe UI';
    this.fontBold = src.fontBold || false;
    this.fontItalic = src.fontItalic || false;
  }

  /* --- Style du contexte canvas --- */

  /** Applique le style de la forme au contexte avant de dessiner */
  applyStyle(ctx, highlight, ghost) {
    ctx.globalAlpha = ghost ? this.opacity * 0.4 : this.opacity;
    if (this.shadowColor && !ghost) {
      ctx.shadowColor = this.shadowColor;
      ctx.shadowBlur = this.shadowBlur;
      ctx.shadowOffsetX = this.shadowOffsetX;
      ctx.shadowOffsetY = this.shadowOffsetY;
    }
    ctx.strokeStyle = highlight ? '#ff6b35' : this.strokeColor;
    ctx.lineWidth = this.lineWidth;
    if (ghost) {
      ctx.setLineDash([6, 4]);
    } else if (this.dashStyle === 'dashed') {
      ctx.setLineDash([12, 6]);
    } else if (this.dashStyle === 'dotted') {
      ctx.setLineDash([2, 4]);
    } else {
      ctx.setLineDash([]);
    }
  }

  /** Remet le contexte a son etat par defaut */
  resetStyle(ctx) {
    ctx.globalAlpha = 1;
    ctx.setLineDash([]);
    ctx.shadowColor = 'transparent';
    ctx.shadowBlur = 0;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 0;
  }

  /* --- Transformations (rotation + deformation) --- */

  /**
   * Applique la rotation et le skew au contexte canvas.
   * Doit etre appele entre ctx.save() et ctx.restore().
   */
  applyTransform(ctx) {
    if (this.rotation === 0 && this.skewX === 0 && this.skewY === 0) return;
    const b = this.getBounds();
    const cx = b.x + b.w / 2, cy = b.y + b.h / 2;
    ctx.translate(cx, cy);
    if (this.rotation) ctx.rotate(this.rotation);
    if (this.skewX || this.skewY) ctx.transform(1, this.skewY, this.skewX, 1, 0, 0);
    ctx.translate(-cx, -cy);
  }

  /** Placeholder — le restauration se fait via ctx.restore() */
  restoreTransform(ctx) {
    // L'appelant doit utiliser ctx.save()/ctx.restore()
  }

  /** Remplit la forme si le remplissage est actif (supporte les degrades) */
  fillShape(ctx) {
    if (!this.useFill) return;
    if (this.gradientType !== 'none') {
      const b = this.getBounds();
      let grad;
      if (this.gradientType === 'radial') {
        const cx = b.x + b.w / 2, cy = b.y + b.h / 2, r = Math.max(b.w, b.h) / 2;
        grad = ctx.createRadialGradient(cx, cy, 0, cx, cy, r);
      } else {
        grad = ctx.createLinearGradient(b.x, b.y, b.x + b.w, b.y + b.h);
      }
      grad.addColorStop(0, this.gradientColor1);
      grad.addColorStop(1, this.gradientColor2);
      ctx.fillStyle = grad;
    } else {
      ctx.fillStyle = this.fillColor;
    }
    ctx.fill();
  }

  /* --- Conversion coordonnees souris → espace local (inverse transform) --- */

  /**
   * Transforme des coordonnees ecran en coordonnees locales
   * en inversant la rotation et le skew.
   * Necessaire pour que hitTest fonctionne sur les formes transformees.
   */
  _toLocal(x, y) {
    if (this.rotation === 0 && this.skewX === 0 && this.skewY === 0) return { x, y };
    const b = this.getBoundsRaw ? this.getBoundsRaw() : this.getBounds();
    const cx = b.x + b.w / 2, cy = b.y + b.h / 2;
    let lx = x - cx, ly = y - cy;
    // Inverse de la rotation
    if (this.rotation) {
      const cos = Math.cos(-this.rotation), sin = Math.sin(-this.rotation);
      const rx = lx * cos - ly * sin, ry = lx * sin + ly * cos;
      lx = rx; ly = ry;
    }
    // Inverse du skew
    if (this.skewX || this.skewY) {
      const det = 1 - this.skewX * this.skewY;
      if (Math.abs(det) > 0.001) {
        const ix = (lx - this.skewX * ly) / det;
        const iy = (ly - this.skewY * lx) / det;
        lx = ix; ly = iy;
      }
    }
    return { x: lx + cx, y: ly + cy };
  }

  /* --- Methodes geometriques (a surcharger) --- */

  /** Teste si le point (x,y) touche la forme */
  hitTest(x, y) { return false; }

  /** Retourne le rectangle englobant {x, y, w, h} */
  getBounds() { return { x: 0, y: 0, w: 0, h: 0 }; }

  /** Deplace la forme de (dx, dy) */
  move(dx, dy) {}

  /** Redimensionne la forme pour tenir dans le nouveau rectangle */
  resize(nb) {}

  /* --- Serialisation --- */

  /** Exporte la forme en objet JSON */
  serialize() { return { ...this }; }
}
