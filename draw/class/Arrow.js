/**
 * Draw - Arrow
 * Forme fleche definie par deux points (x1,y1) -> (x2,y2).
 * Dessine une ligne avec une pointe de fleche a l'extremite (x2, y2).
 * La pointe est composee de deux segments formant un angle de 30 degres (PI/6)
 * de chaque cote de la direction de la fleche.
 *
 * Dependances : Shape, LineShape (utilise _distToSegment pour le hit test)
 */

class Arrow extends Shape {

  /**
   * @param {number} x1 - X du point de depart
   * @param {number} y1 - Y du point de depart
   * @param {number} x2 - X du point d'arrivee (pointe de la fleche)
   * @param {number} y2 - Y du point d'arrivee
   */
  constructor(x1, y1, x2, y2) {
    super('arrow');
    this.x1 = x1;
    this.y1 = y1;
    this.x2 = x2;
    this.y2 = y2;
  }

  /**
   * Dessine la fleche : d'abord la ligne principale, puis la pointe.
   * La pointe est calculee a partir de l'angle de la ligne et d'une longueur fixe de 14px.
   */
  draw(ctx, highlight = false, ghost = false) {
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);

    const headLen = 14; // Longueur des branches de la pointe de fleche
    const angle = Math.atan2(this.y2 - this.y1, this.x2 - this.x1); // Angle de la ligne

    // Dessiner la ligne principale
    ctx.beginPath();
    ctx.moveTo(this.x1, this.y1);
    ctx.lineTo(this.x2, this.y2);
    ctx.stroke();

    // Dessiner la pointe de fleche (deux segments a +/- PI/6 de l'angle principal)
    ctx.beginPath();
    ctx.moveTo(this.x2, this.y2);
    ctx.lineTo(this.x2 - headLen * Math.cos(angle - Math.PI / 6), this.y2 - headLen * Math.sin(angle - Math.PI / 6));
    ctx.moveTo(this.x2, this.y2);
    ctx.lineTo(this.x2 - headLen * Math.cos(angle + Math.PI / 6), this.y2 - headLen * Math.sin(angle + Math.PI / 6));
    ctx.stroke();

    this.resetStyle(ctx);
    ctx.restore();
  }

  /** Retourne une copie independante de cette fleche, avec le meme style. */
  clone() {
    const c = new Arrow(this.x1, this.y1, this.x2, this.y2);
    c.copyStyle(this);
    return c;
  }

  /**
   * Test de collision : reutilise _distToSegment de LineShape via son prototype.
   * Cela evite de dupliquer la logique de calcul de distance point-segment.
   */
  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    return LineShape.prototype._distToSegment(x, y, this.x1, this.y1, this.x2, this.y2) <= 6;
  }

  /** Retourne le rectangle englobant de la fleche. */
  getBounds() {
    return {
      x: Math.min(this.x1, this.x2),
      y: Math.min(this.y1, this.y2),
      w: Math.abs(this.x2 - this.x1),
      h: Math.abs(this.y2 - this.y1)
    };
  }

  /** Deplace les deux extremites de la fleche d'un delta (dx, dy). */
  move(dx, dy) {
    this.x1 += dx; this.y1 += dy;
    this.x2 += dx; this.y2 += dy;
  }

  /**
   * Redimensionne la fleche proportionnellement a la nouvelle bounding box.
   */
  resize(nb) {
    const ob = this.getBounds();
    const sx = ob.w > 0 ? nb.w / ob.w : 1, sy = ob.h > 0 ? nb.h / ob.h : 1;
    this.x1 = nb.x + (this.x1 - ob.x) * sx; this.y1 = nb.y + (this.y1 - ob.y) * sy;
    this.x2 = nb.x + (this.x2 - ob.x) * sx; this.y2 = nb.y + (this.y2 - ob.y) * sy;
  }
}
