/**
 * Draw - BezierShape
 * Courbe de Bezier cubique definie par 4 points :
 *   p0 (debut), p1 (controle 1), p2 (controle 2), p3 (fin)
 * Clic pour placer les points, drag pour ajuster les controles.
 *
 * Dependances : Shape, LineShape (_distToSegment)
 */
class BezierShape extends Shape {

  constructor(p0, p1, p2, p3) {
    super('bezier');
    this.p0 = p0 || { x: 0, y: 0 };
    this.p1 = p1 || { x: 0, y: 0 };
    this.p2 = p2 || { x: 0, y: 0 };
    this.p3 = p3 || { x: 0, y: 0 };
  }

  draw(ctx, highlight = false, ghost = false) {
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);
    ctx.beginPath();
    ctx.moveTo(this.p0.x, this.p0.y);
    ctx.bezierCurveTo(this.p1.x, this.p1.y, this.p2.x, this.p2.y, this.p3.x, this.p3.y);
    ctx.stroke();

    // En mode selection, dessiner les poignees de controle
    if (highlight) {
      ctx.setLineDash([3, 3]);
      ctx.strokeStyle = 'rgba(0,212,255,0.5)';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(this.p0.x, this.p0.y);
      ctx.lineTo(this.p1.x, this.p1.y);
      ctx.moveTo(this.p3.x, this.p3.y);
      ctx.lineTo(this.p2.x, this.p2.y);
      ctx.stroke();
      ctx.setLineDash([]);

      // Points de controle
      [this.p1, this.p2].forEach(p => {
        ctx.fillStyle = '#e67e22';
        ctx.beginPath();
        ctx.arc(p.x, p.y, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 1.5;
        ctx.stroke();
      });
      // Points d'extremite
      [this.p0, this.p3].forEach(p => {
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
    const c = new BezierShape(
      { ...this.p0 }, { ...this.p1 }, { ...this.p2 }, { ...this.p3 }
    );
    c.copyStyle(this);
    return c;
  }

  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    // Echantillonner la courbe en segments et tester la distance
    const steps = 30;
    for (let i = 0; i < steps; i++) {
      const t1 = i / steps, t2 = (i + 1) / steps;
      const a = this._evalBezier(t1);
      const b = this._evalBezier(t2);
      if (LineShape.prototype._distToSegment(x, y, a.x, a.y, b.x, b.y) <= 6) return true;
    }
    return false;
  }

  _evalBezier(t) {
    const mt = 1 - t;
    return {
      x: mt * mt * mt * this.p0.x + 3 * mt * mt * t * this.p1.x + 3 * mt * t * t * this.p2.x + t * t * t * this.p3.x,
      y: mt * mt * mt * this.p0.y + 3 * mt * mt * t * this.p1.y + 3 * mt * t * t * this.p2.y + t * t * t * this.p3.y
    };
  }

  getBounds() {
    const xs = [this.p0.x, this.p1.x, this.p2.x, this.p3.x];
    const ys = [this.p0.y, this.p1.y, this.p2.y, this.p3.y];
    const minX = Math.min(...xs), minY = Math.min(...ys);
    return {
      x: minX, y: minY,
      w: Math.max(...xs) - minX,
      h: Math.max(...ys) - minY
    };
  }

  move(dx, dy) {
    [this.p0, this.p1, this.p2, this.p3].forEach(p => { p.x += dx; p.y += dy; });
  }

  resize(nb) {
    const ob = this.getBounds();
    const sx = ob.w > 0 ? nb.w / ob.w : 1, sy = ob.h > 0 ? nb.h / ob.h : 1;
    [this.p0, this.p1, this.p2, this.p3].forEach(p => {
      p.x = nb.x + (p.x - ob.x) * sx;
      p.y = nb.y + (p.y - ob.y) * sy;
    });
  }

  serialize() {
    return { ...this, p0: { ...this.p0 }, p1: { ...this.p1 }, p2: { ...this.p2 }, p3: { ...this.p3 } };
  }
}
