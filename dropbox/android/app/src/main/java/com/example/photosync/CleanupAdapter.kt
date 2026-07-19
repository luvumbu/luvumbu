package com.example.photosync

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.CheckBox
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import coil.load

/**
 * Grille de photos présentes sur le serveur mais absentes du téléphone.
 * Chaque photo est sélectionnable (case à cocher) pour suppression serveur.
 */
class CleanupAdapter(
    private val thumbUrl: (Long) -> String,
) : RecyclerView.Adapter<CleanupAdapter.VH>() {

    private val items = mutableListOf<ServerPhoto>()
    private val selected = LinkedHashSet<Long>()

    /** Appelé quand la sélection change (pour rafraîchir le compteur du bouton). */
    var onSelectionChanged: (() -> Unit)? = null

    fun submit(list: List<ServerPhoto>) {
        items.clear(); items.addAll(list)
        selected.clear()
        notifyDataSetChanged()
        onSelectionChanged?.invoke()
    }

    fun selectedIds(): List<Long> = selected.toList()
    fun selectedCount(): Int = selected.size
    fun itemCount0(): Int = items.size

    fun selectAll(all: Boolean) {
        selected.clear()
        if (all) items.forEach { selected.add(it.id) }
        notifyDataSetChanged()
        onSelectionChanged?.invoke()
    }

    fun removeByIds(ids: Collection<Long>) {
        val set = ids.toHashSet()
        items.removeAll { it.id in set }
        selected.removeAll(set)
        notifyDataSetChanged()
        onSelectionChanged?.invoke()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val v = LayoutInflater.from(parent.context).inflate(R.layout.item_select_photo, parent, false)
        return VH(v)
    }

    override fun getItemCount() = items.size

    override fun onBindViewHolder(holder: VH, position: Int) {
        val photo = items[position]
        holder.image.load(thumbUrl(photo.id)) { crossfade(true) }
        holder.caption.text = photo.name
        bindSelection(holder, photo.id in selected)
        holder.itemView.setOnClickListener {
            val nowSelected = if (photo.id in selected) {
                selected.remove(photo.id); false
            } else {
                selected.add(photo.id); true
            }
            bindSelection(holder, nowSelected)
            onSelectionChanged?.invoke()
        }
    }

    private fun bindSelection(holder: VH, isSelected: Boolean) {
        holder.check.isChecked = isSelected
        holder.overlay.visibility = if (isSelected) View.VISIBLE else View.GONE
    }

    class VH(view: View) : RecyclerView.ViewHolder(view) {
        val image: ImageView = view.findViewById(R.id.photo)
        val overlay: View = view.findViewById(R.id.selectedOverlay)
        val check: CheckBox = view.findViewById(R.id.check)
        val caption: TextView = view.findViewById(R.id.caption)
    }
}
