/**
 * Draw - ShapeFactory
 *
 * Methode statique de deserialisation attachee a la classe Shape.
 * Reconstruit une forme a partir de ses donnees JSON sauvegardees.
 * Doit etre charge APRES toutes les sous-classes de Shape.
 *
 * Dependances : Shape, Circle, Rect, LineShape, EllipseShape,
 *               Triangle, Arrow, Pencil, TextShape, ImageShape
 */

/**
 * Deserialise un objet JSON en instance de Shape.
 * Utilise le champ `type` pour determiner quelle sous-classe instancier.
 *
 * @param {Object} d - Donnees serializees de la forme
 * @returns {Shape|null} - Instance de la forme, ou null si type inconnu
 */
Shape.deserialize = function(d) {
  let s;

  // Creation de la forme selon son type
  switch (d.type) {
    case 'circle':   s = new Circle(d.x, d.y, d.r); break;
    case 'rect':     s = new Rect(d.x, d.y, d.w, d.h); break;
    case 'line':     s = new LineShape(d.x1, d.y1, d.x2, d.y2); break;
    case 'ellipse':  s = new EllipseShape(d.x, d.y, d.rx, d.ry); break;
    case 'triangle': s = new Triangle(d.x1, d.y1, d.x2, d.y2, d.x3, d.y3); break;
    case 'arrow':    s = new Arrow(d.x1, d.y1, d.x2, d.y2); break;
    case 'pencil':   s = new Pencil(d.points); break;
    case 'text':     s = new TextShape(d.x, d.y, d.text, d.fontSize); break;
    case 'image':    s = new ImageShape(d.x, d.y, d.w, d.h, d.dataUrl); break;
    case 'bezier':   s = new BezierShape(d.p0, d.p1, d.p2, d.p3); break;
    case 'polygon':  s = new PolygonShape(d.cx, d.cy, d.radius, d.sides, d.starMode); if (d.innerRadius) s.innerRadius = d.innerRadius; break;
    case 'doublearrow': s = new DoubleArrow(d.x1, d.y1, d.x2, d.y2); break;
    case 'quadratic': s = new QuadraticShape(d.p0, d.p1, d.p2); break;
    default: return null;
  }

  // Restauration des proprietes communes
  if (s) {
    s.name        = d.name || '';
    s.strokeColor = d.strokeColor;
    s.fillColor   = d.fillColor;
    s.useFill     = d.useFill;
    s.lineWidth   = d.lineWidth;
    s.opacity     = d.opacity;
    s.groupId     = d.groupId || null;
    s.rotation    = d.rotation || 0;
    s.skewX       = d.skewX || 0;
    s.skewY       = d.skewY || 0;
    s.dashStyle   = d.dashStyle || 'solid';
    s.locked      = d.locked || false;
    s.visible     = d.visible !== undefined ? d.visible : true;
    s.shadowColor = d.shadowColor || '';
    s.shadowBlur  = d.shadowBlur || 8;
    s.shadowOffsetX = d.shadowOffsetX || 4;
    s.shadowOffsetY = d.shadowOffsetY || 4;
    if (d.borderRadius && s.borderRadius !== undefined) s.borderRadius = d.borderRadius;
    s.gradientType   = d.gradientType || 'none';
    s.gradientColor1 = d.gradientColor1 || '#00d4ff';
    s.gradientColor2 = d.gradientColor2 || '#ff6b35';
    s.fontFamily     = d.fontFamily || 'Segoe UI';
    s.fontBold       = d.fontBold || false;
    s.fontItalic     = d.fontItalic || false;
  }

  return s;
};
