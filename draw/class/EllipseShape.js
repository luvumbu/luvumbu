/**
 * Draw - EllipseShape
 * Forme geometrique ellipse definie par un centre (x, y) et deux rayons (rx, ry).
 * Similaire au cercle mais avec des rayons independants sur chaque axe,
 * permettant des formes ovales.
 *
 * Dependances : Shape
 */

class EllipseShape extends Shape {

  /**
   * @param {number} x  - Coordonnee X du centre
   * @param {number} y  - Coordonnee Y du centre
   * @param {number} rx - Rayon horizontal (toujours positif)
   * @param {number} ry - Rayon vertical (toujours positif)
   */
  constructor(x, y, rx, ry) {
    super('ellipse');
    this.x = x;
    this.y = y;
    this.rx = Math.abs(rx); // Garantit un rayon positif
    this.ry = Math.abs(ry);
  }

  /**
   * Dessine l'ellipse sur le canvas.
   * Les rayons sont proteges par || 1 pour eviter une erreur si le rayon est 0
   * (ctx.ellipse n'accepte pas un rayon de 0).
   */
  draw(ctx, highlight = false, ghost = false) {
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);
    ctx.beginPath();
    // Les parametres : centre, rayons, rotation (0), angles de debut et fin (cercle complet)
    ctx.ellipse(this.x, this.y, this.rx || 1, this.ry || 1, 0, 0, Math.PI * 2);
    this.fillShape(ctx);
    ctx.stroke();
    this.resetStyle(ctx);
    ctx.restore();
  }

  /** Retourne une copie independante de cette ellipse, avec le meme style. */
  clone() {
    const c = new EllipseShape(this.x, this.y, this.rx, this.ry);
    c.copyStyle(this);
    return c;
  }

  /**
   * Test de collision point-ellipse.
   * Utilise l'equation implicite de l'ellipse : (dx/rx)^2 + (dy/ry)^2 <= 1
   * Une marge de 4px est ajoutee aux rayons pour faciliter la selection.
   */
  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    return ((x - this.x) ** 2 / (this.rx + 4) ** 2) + ((y - this.y) ** 2 / (this.ry + 4) ** 2) <= 1;
  }

  /** Retourne le rectangle englobant de l'ellipse. */
  getBounds() {
    return {
      x: this.x - this.rx,
      y: this.y - this.ry,
      w: this.rx * 2,
      h: this.ry * 2
    };
  }

  /** Deplace l'ellipse d'un delta (dx, dy). */
  move(dx, dy) {
    this.x += dx;
    this.y += dy;
  }

  /**
   * Redimensionne l'ellipse pour s'inscrire dans la nouvelle bounding box.
   * Chaque rayon est calcule independamment a partir de la largeur et hauteur.
   * Minimum de 2px pour eviter une ellipse invisible.
   */
  resize(nb) {
    this.rx = Math.max(2, nb.w / 2);
    this.ry = Math.max(2, nb.h / 2);
    // Recentrer l'ellipse dans la nouvelle bounding box
    this.x = nb.x + nb.w / 2;
    this.y = nb.y + nb.h / 2;
  }
}
