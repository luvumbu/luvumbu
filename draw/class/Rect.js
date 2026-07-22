/**
 * Draw - Rect
 * Forme geometrique rectangle definie par une origine (x, y) et des dimensions (w, h).
 * Gere les cas ou la largeur ou la hauteur sont negatives (dessin en sens inverse)
 * en normalisant les coordonnees dans hitTest et getBounds.
 *
 * Dependances : Shape
 */

class Rect extends Shape {

  /**
   * @param {number} x - Coordonnee X du coin superieur gauche
   * @param {number} y - Coordonnee Y du coin superieur gauche
   * @param {number} w - Largeur (peut etre negative pendant le dessin interactif)
   * @param {number} h - Hauteur (peut etre negative pendant le dessin interactif)
   */
  constructor(x, y, w, h) {
    super('rect');
    this.x = x;
    this.y = y;
    this.w = w;
    this.h = h;
    this.borderRadius = 0;
  }

  /**
   * Dessine le rectangle sur le canvas.
   * @param {CanvasRenderingContext2D} ctx - Contexte de rendu
   * @param {boolean} highlight - Si true, affiche le style de selection
   * @param {boolean} ghost - Si true, affiche en mode fantome
   */
  draw(ctx, highlight = false, ghost = false) {
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);
    ctx.beginPath();
    if (this.borderRadius > 0) {
      const r = Math.min(this.borderRadius, Math.abs(this.w) / 2, Math.abs(this.h) / 2);
      const nx = Math.min(this.x, this.x + this.w), ny = Math.min(this.y, this.y + this.h);
      const nw = Math.abs(this.w), nh = Math.abs(this.h);
      ctx.moveTo(nx + r, ny);
      ctx.lineTo(nx + nw - r, ny);
      ctx.arcTo(nx + nw, ny, nx + nw, ny + r, r);
      ctx.lineTo(nx + nw, ny + nh - r);
      ctx.arcTo(nx + nw, ny + nh, nx + nw - r, ny + nh, r);
      ctx.lineTo(nx + r, ny + nh);
      ctx.arcTo(nx, ny + nh, nx, ny + nh - r, r);
      ctx.lineTo(nx, ny + r);
      ctx.arcTo(nx, ny, nx + r, ny, r);
      ctx.closePath();
    } else {
      ctx.rect(this.x, this.y, this.w, this.h);
    }
    this.fillShape(ctx);
    ctx.stroke();
    this.resetStyle(ctx);
    ctx.restore();
  }

  /** Retourne une copie independante de ce rectangle, avec le meme style. */
  clone() {
    const c = new Rect(this.x, this.y, this.w, this.h);
    c.borderRadius = this.borderRadius;
    c.copyStyle(this);
    return c;
  }

  /**
   * Test de collision point-rectangle.
   * Normalise les coordonnees pour gerer les dimensions negatives,
   * puis ajoute une marge de 4px pour faciliter la selection.
   */
  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    // Normaliser : recalculer l'origine et les dimensions en valeurs positives
    const nx = Math.min(this.x, this.x + this.w), ny = Math.min(this.y, this.y + this.h);
    const nw = Math.abs(this.w), nh = Math.abs(this.h);
    return x >= nx - 4 && x <= nx + nw + 4 && y >= ny - 4 && y <= ny + nh + 4;
  }

  /**
   * Retourne le rectangle englobant normalise (dimensions toujours positives).
   */
  getBounds() {
    return {
      x: Math.min(this.x, this.x + this.w),
      y: Math.min(this.y, this.y + this.h),
      w: Math.abs(this.w),
      h: Math.abs(this.h)
    };
  }

  /** Deplace le rectangle d'un delta (dx, dy). */
  move(dx, dy) {
    this.x += dx;
    this.y += dy;
  }

  /** Redimensionne le rectangle en appliquant directement la nouvelle bounding box. */
  resize(nb) {
    this.x = nb.x;
    this.y = nb.y;
    this.w = nb.w;
    this.h = nb.h;
  }
}
