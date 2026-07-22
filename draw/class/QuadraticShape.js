/**
 * Draw - QuadraticShape
 * Courbe de Bezier quadratique (3 points : debut, controle, fin).
 *
 * Dependances : Shape, LineShape
 */
class QuadraticShape extends Shape {

  constructor(p0, p1, p2) {
    super('quadratic');
    this.p0 = p0 || { x: 0, y: 0 };
    this.p1 = p1 || { x: 0, y: 0 };
    this.p2 = p2 || { x: 0, y: 0 };
  }

  draw(ctx, highlight = false, ghost = false) {
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);
    ctx.beginPath();
    ctx.moveTo(this.p0.x, this.p0.y);
    ctx.quadraticCurveTo(this.p1.x, this.p1.y, this.p2.x, this.p2.y);
    ctx.stroke();

    if (highlight) {
      ctx.setLineDash([3, 3]);
      ctx.strokeStyle = 'rgba(0,212,255,0.5)';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(this.p0.x, this.p0.y);
      ctx.lineTo(this.p1.x, this.p1.y);
      ctx.lineTo(this.p2.x, this.p2.y);
      ctx.stroke();
      ctx.setLineDash([]);

      ctx.fillStyle = '#e67e22';
      ctx.beginPath();
      ctx.arc(this.p1.x, this.p1.y, 5, 0, Math.PI * 2);
      ctx.fill();
      [this.p0, this.p2].forEach(p => {
        ctx.fillStyle = '#00d4ff';
        ctx.beginPath();
        ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
        ctx.fill();
      });
    }

    this.resetStyle(ctx);
    ctx.restore();
  }

  clone() {
    const c = new QuadraticShape({ ...this.p0 }, { ...this.p1 }, { ...this.p2 });
    c.copyStyle(this);
    return c;
  }

  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    const steps = 25;
    for (let i = 0; i < steps; i++) {
      const t1 = i / steps, t2 = (i + 1) / steps;
      const a = this._eval(t1), b = this._eval(t2);
      if (LineShape.prototype._distToSegment(x, y, a.x, a.y, b.x, b.y) <= 6) return true;
    }
    return false;
  }

  _eval(t) {
    const mt = 1 - t;
    return {
      x: mt * mt * this.p0.x + 2 * mt * t * this.p1.x + t * t * this.p2.x,
      y: mt * mt * this.p0.y + 2 * mt * t * this.p1.y + t * t * this.p2.y
    };
  }

  getBounds() {
    const xs = [this.p0.x, this.p1.x, this.p2.x];
    const ys = [this.p0.y, this.p1.y, this.p2.y];
    const minX = Math.min(...xs), minY = Math.min(...ys);
    return { x: minX, y: minY, w: Math.max(...xs) - minX, h: Math.max(...ys) - minY };
  }

  move(dx, dy) {
    [this.p0, this.p1, this.p2].forEach(p => { p.x += dx; p.y += dy; });
  }

  resize(nb) {
    const ob = this.getBounds();
    const sx = ob.w > 0 ? nb.w / ob.w : 1, sy = ob.h > 0 ? nb.h / ob.h : 1;
    [this.p0, this.p1, this.p2].forEach(p => {
      p.x = nb.x + (p.x - ob.x) * sx;
      p.y = nb.y + (p.y - ob.y) * sy;
    });
  }

  serialize() {
    return { ...this, p0: { ...this.p0 }, p1: { ...this.p1 }, p2: { ...this.p2 } };
  }
}
