/**
 * Draw - Circle
 * Forme geometrique cercle definie par un centre (x, y) et un rayon (r).
 * Herite de Shape et implemente toutes les methodes standard :
 * dessin, clonage, test de collision, deplacement et redimensionnement.
 *
 * Dependances : Shape
 */

class Circle extends Shape {

  /**
   * @param {number} x - Coordonnee X du centre
   * @param {number} y - Coordonnee Y du centre
   * @param {number} r - Rayon du cercle (toujours positif grace a Math.abs)
   */
  constructor(x, y, r) {
    super('circle');
    this.x = x;
    this.y = y;
    this.r = Math.abs(r); // Garantit un rayon positif meme si la valeur passee est negative
  }

  /**
   * Dessine le cercle sur le canvas.
   * @param {CanvasRenderingContext2D} ctx - Contexte de rendu
   * @param {boolean} highlight - Si true, affiche le style de selection
   * @param {boolean} ghost - Si true, affiche en mode fantome (semi-transparent)
   */
  draw(ctx, highlight = false, ghost = false) {
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);
    ctx.beginPath();
    // Arc complet de 0 a 2*PI pour tracer un cercle entier
    ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
    this.fillShape(ctx);
    ctx.stroke();
    this.resetStyle(ctx);
    ctx.restore();
  }

  /** Retourne une copie independante de ce cercle, avec le meme style. */
  clone() {
    const c = new Circle(this.x, this.y, this.r);
    c.copyStyle(this);
    return c;
  }

  /**
   * Test de collision point-cercle.
   * Ajoute une marge de 4px pour faciliter la selection.
   */
  hitTest(px, py) {
    // Convertir les coordonnees globales en coordonnees locales (prend en compte les transformations)
    const { x, y } = this._toLocal(px, py);
    return Math.sqrt((x - this.x) ** 2 + (y - this.y) ** 2) <= this.r + 4;
  }

  /** Retourne le rectangle englobant (bounding box) du cercle. */
  getBounds() {
    return {
      x: this.x - this.r,
      y: this.y - this.r,
      w: this.r * 2,
      h: this.r * 2
    };
  }

  /** Deplace le cercle d'un delta (dx, dy). */
  move(dx, dy) {
    this.x += dx;
    this.y += dy;
  }

  /**
   * Redimensionne le cercle pour s'inscrire dans la nouvelle bounding box.
   * Le rayon est determine par la plus petite dimension (largeur ou hauteur)
   * afin de conserver la forme circulaire.
   */
  resize(nb) {
    this.r = Math.max(2, Math.min(nb.w, nb.h) / 2);
    // Recentrer le cercle dans la nouvelle bounding box
    this.x = nb.x + nb.w / 2;
    this.y = nb.y + nb.h / 2;
  }
}
