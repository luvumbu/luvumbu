/**
 * Draw - PolygonShape
 * Polygone regulier defini par un centre, un rayon et un nombre de cotes.
 * Supporte aussi le mode etoile (starMode) avec un rayon interieur.
 *
 * Dependances : Shape
 */
class PolygonShape extends Shape {

  constructor(cx, cy, radius, sides = 6, starMode = false) {
    super('polygon');
    this.cx = cx;
    this.cy = cy;
    this.radius = Math.abs(radius);
    this.sides = Math.max(3, sides);
    this.starMode = starMode;
    this.innerRadius = this.radius * 0.45;
  }

  _getPoints() {
    const pts = [];
    const total = this.starMode ? this.sides * 2 : this.sides;
    for (let i = 0; i < total; i++) {
      const angle = (Math.PI * 2 * i / total) - Math.PI / 2;
      const r = (this.starMode && i % 2 === 1) ? this.innerRadius : this.radius;
      pts.push({ x: this.cx + r * Math.cos(angle), y: this.cy + r * Math.sin(angle) });
    }
    return pts;
  }

  draw(ctx, highlight = false, ghost = false) {
    const pts = this._getPoints();
    if (pts.length < 3) return;
    ctx.save();
    this.applyTransform(ctx);
    this.applyStyle(ctx, highlight, ghost);
    ctx.beginPath();
    ctx.moveTo(pts[0].x, pts[0].y);
    for (let i = 1; i < pts.length; i++) ctx.lineTo(pts[i].x, pts[i].y);
    ctx.closePath();
    this.fillShape(ctx);
    ctx.stroke();
    this.resetStyle(ctx);
    ctx.restore();
  }

  clone() {
    const c = new PolygonShape(this.cx, this.cy, this.radius, this.sides, this.starMode);
    c.innerRadius = this.innerRadius;
    c.copyStyle(this);
    return c;
  }

  hitTest(px, py) {
    const { x, y } = this._toLocal(px, py);
    // Point-in-polygon (ray casting)
    const pts = this._getPoints();
    let inside = false;
    for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
      const xi = pts[i].x, yi = pts[i].y, xj = pts[j].x, yj = pts[j].y;
      if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) {
        inside = !inside;
      }
    }
    if (inside) return true;
    // Aussi tester la proximite des bords
    for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
      if (LineShape.prototype._distToSegment(x, y, pts[j].x, pts[j].y, pts[i].x, pts[i].y) <= 6) return true;
    }
    return false;
  }

  getBounds() {
    const pts = this._getPoints();
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    pts.forEach(p => { minX = Math.min(minX, p.x); minY = Math.min(minY, p.y); maxX = Math.max(maxX, p.x); maxY = Math.max(maxY, p.y); });
    return { x: minX, y: minY, w: maxX - minX, h: maxY - minY };
  }

  move(dx, dy) { this.cx += dx; this.cy += dy; }

  resize(nb) {
    this.cx = nb.x + nb.w / 2;
    this.cy = nb.y + nb.h / 2;
    const oldR = this.radius || 1;
    this.radius = Math.max(4, Math.min(nb.w, nb.h) / 2);
    this.innerRadius = this.innerRadius * (this.radius / oldR);
  }

  serialize() { return { ...this }; }
}
