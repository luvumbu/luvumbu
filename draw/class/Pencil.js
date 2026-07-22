/**
 * Draw - Pencil
 * Forme dessin libre (main levee) composee d'un tableau de points {x, y}.
 * Chaque segment entre deux points consecutifs est dessine comme une ligne.
 * Le test de collision verifie la distance a chaque segment individuellement.
 *
 * Dependances : Shape, LineShape (utilise _distToSegment pour le hit test)
 */

class Pencil extends Shape {

  /**
   * @param {Array<{x: number, y: number}>} points - Tableau de points du trace
   */
  constructor(points = []) {
    super('pencil');
    this.points = points;
  }

  /**
   * Dessine le trace en reliant tous les points par des segments.
   * Ne dessine rien s'il y a moins de 2 points.
   */
  draw(ctx, highlight = false, ghost = false) {
    if (this.points.length < 2) return; // Pas assez de points pour tracer une ligne
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);
    ctx.beginPath();
    ctx.moveTo(this.points[0].x, this.points[0].y);
    for (let i = 1; i < this.points.length; i++) {
      ctx.lineTo(this.points[i].x, this.points[i].y);
    }
    ctx.stroke();
    this.resetStyle(ctx);
    ctx.restore();
  }

  /**
   * Retourne une copie independante de ce trace.
   * Les points sont clones en profondeur (spread) pour eviter les references partagees.
   */
  clone() {
    const c = new Pencil(this.points.map(p => ({ ...p })));
    c.copyStyle(this);
    return c;
  }

  /**
   * Test de collision : verifie si le point est a moins de 6px de l'un des segments.
   * Parcourt chaque paire de points consecutifs et reutilise _distToSegment de LineShape.
   */
  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    for (let i = 1; i < this.points.length; i++) {
      if (LineShape.prototype._distToSegment(x, y, this.points[i - 1].x, this.points[i - 1].y, this.points[i].x, this.points[i].y) <= 6) {
        return true;
      }
    }
    return false;
  }

  /**
   * Retourne le rectangle englobant de tous les points du trace.
   * Retourne une box vide si le tableau de points est vide.
   */
  getBounds() {
    if (!this.points.length) return { x: 0, y: 0, w: 0, h: 0 };
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    this.points.forEach(p => {
      minX = Math.min(minX, p.x);
      minY = Math.min(minY, p.y);
      maxX = Math.max(maxX, p.x);
      maxY = Math.max(maxY, p.y);
    });
    return { x: minX, y: minY, w: maxX - minX, h: maxY - minY };
  }

  /** Deplace tous les points du trace d'un delta (dx, dy). */
  move(dx, dy) {
    this.points.forEach(p => { p.x += dx; p.y += dy; });
  }

  /**
   * Redimensionne le trace en recalculant chaque point proportionnellement
   * a la nouvelle bounding box. Chaque point est repositionne selon
   * son offset relatif dans l'ancienne box.
   */
  resize(nb) {
    const ob = this.getBounds();
    const sx = ob.w > 0 ? nb.w / ob.w : 1, sy = ob.h > 0 ? nb.h / ob.h : 1;
    this.points.forEach(p => {
      p.x = nb.x + (p.x - ob.x) * sx;
      p.y = nb.y + (p.y - ob.y) * sy;
    });
  }
}
