package com.example.photosync

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.CheckBox
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import coil.load

/** Grille de photos LOCALES (miniature depuis l'appareil) sélectionnables. */
class LocalSelectAdapter : RecyclerView.Adapter<LocalSelectAdapter.VH>() {

    private val items = mutableListOf<LocalPhoto>()
    private val selected = LinkedHashSet<Long>()
    var onSelectionChanged: (() -> Unit)? = null

    fun submit(list: List<LocalPhoto>) {
        items.clear(); items.addAll(list)
        selected.clear()
        notifyDataSetChanged()
        onSelectionChanged?.invoke()
    }

    fun selectedItems(): List<LocalPhoto> = items.filter { it.id in selected }
    fun selectedCount(): Int = selected.size
    fun count(): Int = items.size

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
        holder.image.load(photo.uri) { crossfade(true) }
        holder.caption.text = photo.name
        bind(holder, photo.id in selected)
        holder.itemView.setOnClickListener {
            val now = if (photo.id in selected) { selected.remove(photo.id); false }
                      else { selected.add(photo.id); true }
            bind(holder, now)
            onSelectionChanged?.invoke()
        }
    }

    private fun bind(holder: VH, isSelected: Boolean) {
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
