/**
 * Draw - TextShape
 * Forme texte multi-ligne avec support bold/italic/font family.
 *
 * Dependances : Shape
 */
class TextShape extends Shape {

  constructor(x, y, text = '', fontSize = 16) {
    super('text');
    this.x = x;
    this.y = y;
    this.text = text;
    this.fontSize = fontSize;
  }

  _getFont() {
    let f = '';
    if (this.fontItalic) f += 'italic ';
    if (this.fontBold) f += 'bold ';
    f += `${this.fontSize}px '${this.fontFamily || 'Segoe UI'}', sans-serif`;
    return f;
  }

  _getLines() {
    return this.text.split('\n');
  }

  draw(ctx, highlight = false, ghost = false) {
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);

    ctx.font = this._getFont();
    ctx.textBaseline = 'top';
    ctx.fillStyle = this.useFill ? this.fillColor : this.strokeColor;

    const lines = this._getLines();
    const lineH = this.fontSize * 1.2;
    lines.forEach((line, i) => {
      ctx.fillText(line, this.x, this.y + i * lineH);
    });

    if (highlight) {
      const b = this.getBounds();
      ctx.strokeStyle = '#ff6b35';
      ctx.lineWidth = 1;
      ctx.setLineDash([4, 3]);
      ctx.strokeRect(b.x - 2, b.y - 2, b.w + 4, b.h + 4);
    }

    this.resetStyle(ctx);
    ctx.restore();
  }

  clone() {
    const c = new TextShape(this.x, this.y, this.text, this.fontSize);
    c.copyStyle(this);
    return c;
  }

  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    const b = this.getBounds();
    return x >= b.x - 4 && x <= b.x + b.w + 4 && y >= b.y - 4 && y <= b.y + b.h + 4;
  }

  getBounds() {
    const ctx = TextShape._getCtx();
    ctx.font = this._getFont();
    const lines = this._getLines();
    const lineH = this.fontSize * 1.2;
    let maxW = 0;
    lines.forEach(line => {
      const m = ctx.measureText(line);
      if (m.width > maxW) maxW = m.width;
    });
    return { x: this.x, y: this.y, w: maxW, h: lines.length * lineH };
  }

  static _getCtx() {
    if (!TextShape._measureCtx) {
      const c = document.createElement('canvas');
      TextShape._measureCtx = c.getContext('2d');
    }
    return TextShape._measureCtx;
  }

  move(dx, dy) { this.x += dx; this.y += dy; }

  resize(nb) {
    this.x = nb.x;
    this.y = nb.y;
    this.fontSize = Math.max(6, Math.round(nb.h / Math.max(1, this._getLines().length) / 1.2));
  }
}
