/**
 * Draw - ImageShape
 * Forme image definie par une position (x, y), des dimensions (w, h) et une
 * URL de donnees (dataUrl, typiquement en base64). L'image est chargee de maniere
 * asynchrone via un objet Image. Le dessin ne commence qu'une fois l'image chargee.
 * La methode serialize() exclut les proprietes internes (_img, _loaded)
 * pour permettre la sauvegarde/restauration propre des donnees.
 *
 * Dependances : Shape
 */

class ImageShape extends Shape {

  /**
   * @param {number} x - Coordonnee X du coin superieur gauche
   * @param {number} y - Coordonnee Y du coin superieur gauche
   * @param {number} w - Largeur d'affichage
   * @param {number} h - Hauteur d'affichage
   * @param {string} dataUrl - URL de l'image (data:image/... ou URL classique)
   */
  constructor(x, y, w, h, dataUrl) {
    super('image');
    this.x = x;
    this.y = y;
    this.w = w;
    this.h = h;
    this.dataUrl = dataUrl;
    this._img = null;     // Objet Image interne (non serialise)
    this._loaded = false;  // Indicateur de chargement (non serialise)
    this._loadImage();
  }

  /**
   * Charge l'image de maniere asynchrone.
   * Le flag _loaded passe a true une fois le chargement termine,
   * ce qui permettra au dessin de s'afficher au prochain rendu.
   */
  _loadImage() {
    if (!this.dataUrl) return;
    this._img = new Image();
    this._img.onload = () => { this._loaded = true; if (typeof app !== 'undefined' && app) app.draw(); };
    this._img.onerror = () => { console.error('ImageShape: echec du chargement de l\'image'); };
    this._img.src = this.dataUrl;
  }

  /**
   * Dessine l'image sur le canvas.
   * Ne fait rien si l'image n'est pas encore chargee.
   * En mode ghost, applique une transparence reduite.
   * En mode highlight, dessine un cadre pointille autour de l'image.
   */
  draw(ctx, highlight = false, ghost = false) {
    if (!this._loaded || !this._img) return; // Attendre le chargement complet
    ctx.save();
    this.applyTransform(ctx);

    // Gerer l'opacite : reduite en mode ghost
    ctx.globalAlpha = ghost ? this.opacity * 0.4 : this.opacity;
    if (ghost) { ctx.setLineDash([6, 4]); }

    ctx.drawImage(this._img, this.x, this.y, this.w, this.h);

    // Cadre de selection pointille en mode highlight
    if (highlight) {
      ctx.strokeStyle = '#ff6b35';
      ctx.lineWidth = 2;
      ctx.setLineDash([4, 3]);
      ctx.strokeRect(this.x - 2, this.y - 2, this.w + 4, this.h + 4);
    }

    this.resetStyle(ctx);
    ctx.restore();
  }

  /**
   * Retourne une copie independante de cette image.
   * Le clone est decale de 10px pour etre visible a cote de l'original.
   */
  clone() {
    const c = new ImageShape(this.x + 10, this.y + 10, this.w, this.h, this.dataUrl);
    c.copyStyle(this);
    return c;
  }

  /**
   * Test de collision point-rectangle avec une marge de 4px.
   */
  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    return x >= this.x - 4 && x <= this.x + this.w + 4 && y >= this.y - 4 && y <= this.y + this.h + 4;
  }

  /** Retourne le rectangle englobant de l'image. */
  getBounds() {
    return { x: this.x, y: this.y, w: this.w, h: this.h };
  }

  /** Deplace l'image d'un delta (dx, dy). */
  move(dx, dy) {
    this.x += dx;
    this.y += dy;
  }

  /**
   * Redimensionne l'image avec un minimum de 4px par dimension
   * pour eviter qu'elle ne disparaisse.
   */
  resize(nb) {
    this.x = nb.x;
    this.y = nb.y;
    this.w = Math.max(4, nb.w);
    this.h = Math.max(4, nb.h);
  }

  /**
   * Serialise l'objet pour la sauvegarde.
   * Exclut les proprietes internes _img et _loaded qui ne sont pas serialisables
   * (l'objet Image sera recree au chargement via _loadImage dans le constructeur).
   */
  serialize() {
    const d = { ...this };
    delete d._img;
    delete d._loaded;
    return d;
  }
}
