/**
 * Draw - Triangle
 * Forme geometrique triangle definie par trois sommets (x1,y1), (x2,y2), (x3,y3).
 * Le test de collision utilise la methode des aires : si la somme des aires des
 * trois sous-triangles formes avec le point teste est egale a l'aire totale,
 * alors le point est a l'interieur du triangle.
 *
 * Dependances : Shape
 */

class Triangle extends Shape {

  /**
   * @param {number} x1 - X du premier sommet
   * @param {number} y1 - Y du premier sommet
   * @param {number} x2 - X du deuxieme sommet
   * @param {number} y2 - Y du deuxieme sommet
   * @param {number} x3 - X du troisieme sommet
   * @param {number} y3 - Y du troisieme sommet
   */
  constructor(x1, y1, x2, y2, x3, y3) {
    super('triangle');
    this.x1 = x1; this.y1 = y1;
    this.x2 = x2; this.y2 = y2;
    this.x3 = x3; this.y3 = y3;
  }

  /**
   * Dessine le triangle sur le canvas.
   * Utilise closePath() pour fermer automatiquement le chemin entre le dernier
   * et le premier sommet.
   */
  draw(ctx, highlight = false, ghost = false) {
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);
    ctx.beginPath();
    ctx.moveTo(this.x1, this.y1);
    ctx.lineTo(this.x2, this.y2);
    ctx.lineTo(this.x3, this.y3);
    ctx.closePath();
    this.fillShape(ctx);
    ctx.stroke();
    this.resetStyle(ctx);
    ctx.restore();
  }

  /** Retourne une copie independante de ce triangle, avec le meme style. */
  clone() {
    const c = new Triangle(this.x1, this.y1, this.x2, this.y2, this.x3, this.y3);
    c.copyStyle(this);
    return c;
  }

  /**
   * Test de collision point-triangle par la methode des aires.
   * On calcule l'aire du triangle principal (A) et les trois sous-triangles
   * formes par le point teste avec chaque arete (A1, A2, A3).
   * Si |A - (A1 + A2 + A3)| < 5, le point est considere a l'interieur.
   * La tolerance de 5 offre une marge pour faciliter la selection.
   */
  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    // Fonction utilitaire : aire d'un triangle a partir de 3 sommets (formule du determinant)
    const area = (a, b, c, d, e, f) => Math.abs((a * (d - f) + c * (f - b) + e * (b - d)) / 2);
    const A  = area(this.x1, this.y1, this.x2, this.y2, this.x3, this.y3);
    const A1 = area(x, y, this.x2, this.y2, this.x3, this.y3);
    const A2 = area(this.x1, this.y1, x, y, this.x3, this.y3);
    const A3 = area(this.x1, this.y1, this.x2, this.y2, x, y);
    return Math.abs(A - (A1 + A2 + A3)) < 5;
  }

  /** Retourne le rectangle englobant des trois sommets. */
  getBounds() {
    const minX = Math.min(this.x1, this.x2, this.x3);
    const minY = Math.min(this.y1, this.y2, this.y3);
    return {
      x: minX,
      y: minY,
      w: Math.max(this.x1, this.x2, this.x3) - minX,
      h: Math.max(this.y1, this.y2, this.y3) - minY
    };
  }

  /** Deplace les trois sommets d'un delta (dx, dy). */
  move(dx, dy) {
    this.x1 += dx; this.y1 += dy;
    this.x2 += dx; this.y2 += dy;
    this.x3 += dx; this.y3 += dy;
  }

  /**
   * Redimensionne le triangle en recalculant la position de chaque sommet
   * proportionnellement a la nouvelle bounding box.
   * Chaque sommet est repositionne selon son offset relatif dans l'ancienne box.
   */
  resize(nb) {
    const ob = this.getBounds();
    const sx = ob.w > 0 ? nb.w / ob.w : 1, sy = ob.h > 0 ? nb.h / ob.h : 1;
    this.x1 = nb.x + (this.x1 - ob.x) * sx; this.y1 = nb.y + (this.y1 - ob.y) * sy;
    this.x2 = nb.x + (this.x2 - ob.x) * sx; this.y2 = nb.y + (this.y2 - ob.y) * sy;
    this.x3 = nb.x + (this.x3 - ob.x) * sx; this.y3 = nb.y + (this.y3 - ob.y) * sy;
  }
}
