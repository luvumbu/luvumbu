/**
 * Draw - LineShape
 * Forme geometrique ligne definie par deux points (x1, y1) et (x2, y2).
 * Contient la methode utilitaire _distToSegment qui calcule la distance
 * d'un point a un segment de droite. Cette methode est reutilisee par
 * Arrow et Pencil via LineShape.prototype._distToSegment.
 *
 * Dependances : Shape
 */

class LineShape extends Shape {

  /**
   * @param {number} x1 - Coordonnee X du premier point
   * @param {number} y1 - Coordonnee Y du premier point
   * @param {number} x2 - Coordonnee X du second point
   * @param {number} y2 - Coordonnee Y du second point
   */
  constructor(x1, y1, x2, y2) {
    super('line');
    this.x1 = x1;
    this.y1 = y1;
    this.x2 = x2;
    this.y2 = y2;
  }

  /**
   * Dessine la ligne sur le canvas.
   * Contrairement aux formes fermees, pas de remplissage (fillShape) ici.
   */
  draw(ctx, highlight = false, ghost = false) {
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);
    ctx.beginPath();
    ctx.moveTo(this.x1, this.y1);
    ctx.lineTo(this.x2, this.y2);
    ctx.stroke();
    this.resetStyle(ctx);
    ctx.restore();
  }

  /** Retourne une copie independante de cette ligne, avec le meme style. */
  clone() {
    const c = new LineShape(this.x1, this.y1, this.x2, this.y2);
    c.copyStyle(this);
    return c;
  }

  /**
   * Test de collision : verifie si le point est a moins de 6px du segment.
   */
  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    const d = this._distToSegment(x, y, this.x1, this.y1, this.x2, this.y2);
    return d <= 6;
  }

  /**
   * Calcule la distance minimale entre un point (px, py) et un segment [P1, P2].
   * Utilise la projection orthogonale du point sur la droite portant le segment,
   * puis clamp le parametre t dans [0, 1] pour rester sur le segment.
   *
   * IMPORTANT : Cette methode est partagee avec Arrow et Pencil qui y accedent
   * via LineShape.prototype._distToSegment.
   *
   * @param {number} px - X du point a tester
   * @param {number} py - Y du point a tester
   * @param {number} x1 - X du debut du segment
   * @param {number} y1 - Y du debut du segment
   * @param {number} x2 - X de la fin du segment
   * @param {number} y2 - Y de la fin du segment
   * @returns {number} Distance minimale du point au segment
   */
  _distToSegment(px, py, x1, y1, x2, y2) {
    const dx = x2 - x1, dy = y2 - y1;
    // Cas degenere : le segment est un point unique
    if (dx === 0 && dy === 0) return Math.sqrt((px - x1) ** 2 + (py - y1) ** 2);
    // Parametre t de la projection orthogonale sur la droite (clampe entre 0 et 1)
    let t = ((px - x1) * dx + (py - y1) * dy) / (dx * dx + dy * dy);
    t = Math.max(0, Math.min(1, t));
    // Distance entre le point et sa projection sur le segment
    return Math.sqrt((px - (x1 + t * dx)) ** 2 + (py - (y1 + t * dy)) ** 2);
  }

  /** Retourne le rectangle englobant de la ligne. */
  getBounds() {
    return {
      x: Math.min(this.x1, this.x2),
      y: Math.min(this.y1, this.y2),
      w: Math.abs(this.x2 - this.x1),
      h: Math.abs(this.y2 - this.y1)
    };
  }

  /** Deplace les deux extremites de la ligne d'un delta (dx, dy). */
  move(dx, dy) {
    this.x1 += dx;
    this.y1 += dy;
    this.x2 += dx;
    this.y2 += dy;
  }

  /**
   * Redimensionne la ligne en recalculant les positions des extremites
   * proportionnellement a la nouvelle bounding box.
   * Utilise des facteurs d'echelle sx et sy (valent 1 si la dimension d'origine est nulle).
   */
  resize(nb) {
    const ob = this.getBounds();
    const sx = ob.w > 0 ? nb.w / ob.w : 1, sy = ob.h > 0 ? nb.h / ob.h : 1;
    this.x1 = nb.x + (this.x1 - ob.x) * sx;
    this.y1 = nb.y + (this.y1 - ob.y) * sy;
    this.x2 = nb.x + (this.x2 - ob.x) * sx;
    this.y2 = nb.y + (this.y2 - ob.y) * sy;
  }
}
