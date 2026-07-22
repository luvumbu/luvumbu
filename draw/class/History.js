/**
 * Draw - History (Undo/Redo)
 *
 * Pile d'etats pour gerer l'annulation et le retablissement.
 * Chaque etat est une copie JSON serialisee du LayerManager.
 * Limite a un nombre maximum d'etats (par defaut 50).
 *
 * Dependances : aucune
 */
class History {

  /**
   * @param {number} max - Nombre maximum d'etats conserves
   */
  constructor(max = 50) {
    this.stack = [];   // Pile des etats (chaines JSON)
    this.index = -1;   // Position courante dans la pile
    this.max = max;    // Limite de la pile
  }

  /**
   * Enregistre un nouvel etat.
   * Supprime les etats "futurs" si on a fait des undo avant.
   * @param {Object} state - Etat a sauvegarder (sera converti en JSON)
   */
  push(state) {
    // Tronquer la pile au-dela de la position courante
    this.stack = this.stack.slice(0, this.index + 1);
    this.stack.push(JSON.stringify(state));

    // Supprimer les plus anciens si la pile depasse la limite
    if (this.stack.length > this.max) this.stack.shift();
    this.index = this.stack.length - 1;
  }

  /**
   * Revient a l'etat precedent.
   * @returns {Object|null} - L'etat restaure, ou null si impossible
   */
  undo() {
    if (this.index > 0) {
      this.index--;
      return JSON.parse(this.stack[this.index]);
    }
    return null;
  }

  /**
   * Avance vers l'etat suivant (apres un undo).
   * @returns {Object|null} - L'etat restaure, ou null si impossible
   */
  redo() {
    if (this.index < this.stack.length - 1) {
      this.index++;
      return JSON.parse(this.stack[this.index]);
    }
    return null;
  }

  /** @returns {boolean} - true si un undo est possible */
  canUndo() { return this.index > 0; }

  /** @returns {boolean} - true si un redo est possible */
  canRedo() { return this.index < this.stack.length - 1; }
}
