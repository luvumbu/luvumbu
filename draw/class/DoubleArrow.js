/**
 * Draw - DoubleArrow
 * Fleche bidirectionnelle avec pointes aux deux extremites.
 *
 * Dependances : Shape, LineShape
 */
class DoubleArrow extends Shape {

  constructor(x1, y1, x2, y2) {
    super('doublearrow');
    this.x1 = x1; this.y1 = y1;
    this.x2 = x2; this.y2 = y2;
  }

  draw(ctx, highlight = false, ghost = false) {
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);

    const headLen = 14;
    const angle = Math.atan2(this.y2 - this.y1, this.x2 - this.x1);

    // Ligne principale
    ctx.beginPath();
    ctx.moveTo(this.x1, this.y1);
    ctx.lineTo(this.x2, this.y2);
    ctx.stroke();

    // Pointe avant (x2,y2)
    ctx.beginPath();
    ctx.moveTo(this.x2, this.y2);
    ctx.lineTo(this.x2 - headLen * Math.cos(angle - Math.PI / 6), this.y2 - headLen * Math.sin(angle - Math.PI / 6));
    ctx.moveTo(this.x2, this.y2);
    ctx.lineTo(this.x2 - headLen * Math.cos(angle + Math.PI / 6), this.y2 - headLen * Math.sin(angle + Math.PI / 6));
    ctx.stroke();

    // Pointe arriere (x1,y1)
    const angle2 = angle + Math.PI;
    ctx.beginPath();
    ctx.moveTo(this.x1, this.y1);
    ctx.lineTo(this.x1 - headLen * Math.cos(angle2 - Math.PI / 6), this.y1 - headLen * Math.sin(angle2 - Math.PI / 6));
    ctx.moveTo(this.x1, this.y1);
    ctx.lineTo(this.x1 - headLen * Math.cos(angle2 + Math.PI / 6), this.y1 - headLen * Math.sin(angle2 + Math.PI / 6));
    ctx.stroke();

    this.resetStyle(ctx);
    ctx.restore();
  }

  clone() {
    const c = new DoubleArrow(this.x1, this.y1, this.x2, this.y2);
    c.copyStyle(this);
    return c;
  }

  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    return LineShape.prototype._distToSegment(x, y, this.x1, this.y1, this.x2, this.y2) <= 6;
  }

  getBounds() {
    return {
      x: Math.min(this.x1, this.x2), y: Math.min(this.y1, this.y2),
      w: Math.abs(this.x2 - this.x1), h: Math.abs(this.y2 - this.y1)
    };
  }

  move(dx, dy) { this.x1 += dx; this.y1 += dy; this.x2 += dx; this.y2 += dy; }

  resize(nb) {
    const ob = this.getBounds();
    const sx = ob.w > 0 ? nb.w / ob.w : 1, sy = ob.h > 0 ? nb.h / ob.h : 1;
    this.x1 = nb.x + (this.x1 - ob.x) * sx; this.y1 = nb.y + (this.y1 - ob.y) * sy;
    this.x2 = nb.x + (this.x2 - ob.x) * sx; this.y2 = nb.y + (this.y2 - ob.y) * sy;
  }
}
