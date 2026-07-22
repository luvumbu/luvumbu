package com.frontback.dualcam.gallery

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.frontback.dualcam.R
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class MediaAdapter(
    private var items: List<MediaItem>,
    private val isUploaded: (MediaItem) -> Boolean,
    private val onUpload: (MediaItem) -> Unit,
    private val onClick: (MediaItem) -> Unit,
    private val onSelectionChanged: (Int) -> Unit = {}
) : RecyclerView.Adapter<MediaAdapter.VH>() {

    private val dateFmt = SimpleDateFormat("dd/MM HH:mm", Locale.getDefault())

    /** Mode sélection multiple (cases à cocher) actif ? */
    private var selectionMode = false
    /** Chemins des éléments cochés. */
    private val selected = linkedSetOf<String>()

    class VH(view: View) : RecyclerView.ViewHolder(view) {
        val thumb: ImageView = view.findViewById(R.id.thumb)
        val playIcon: ImageView = view.findViewById(R.id.playIcon)
        val label: TextView = view.findViewById(R.id.label)
        val uploadBtn: TextView = view.findViewById(R.id.uploadBtn)
        val uploadedBadge: TextView = view.findViewById(R.id.uploadedBadge)
        val selectOverlay: View = view.findViewById(R.id.selectOverlay)
        val selectCheck: TextView = view.findViewById(R.id.selectCheck)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val v = LayoutInflater.from(parent.context).inflate(R.layout.item_media, parent, false)
        return VH(v)
    }

    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]
        ThumbLoader.load(item.path, item.isVideo, holder.thumb)
        holder.playIcon.visibility = if (item.isVideo && !selectionMode) View.VISIBLE else View.GONE
        val kind = if (item.isVideo) "🎬" else "📷"
        holder.label.text = "$kind ${dateFmt.format(Date(item.lastModified))}"

        if (selectionMode) {
            // Mode sélection : coche + voile ; on masque les boutons d'envoi.
            val isSel = selected.contains(item.path)
            holder.uploadBtn.visibility = View.GONE
            holder.uploadedBadge.visibility = View.GONE
            holder.selectCheck.visibility = View.VISIBLE
            holder.selectCheck.text = if (isSel) "☑" else "☐"
            holder.selectOverlay.visibility = if (isSel) View.VISIBLE else View.GONE
            holder.itemView.setOnClickListener { toggle(item, position) }
        } else {
            // Mode normal : clic = ouvrir (Voir / Plus d'infos) ; bouton d'envoi individuel.
            holder.selectCheck.visibility = View.GONE
            holder.selectOverlay.visibility = View.GONE
            val sent = isUploaded(item)
            holder.uploadedBadge.visibility = if (sent) View.VISIBLE else View.GONE
            holder.uploadBtn.visibility = if (sent) View.GONE else View.VISIBLE
            holder.uploadBtn.setOnClickListener { onUpload(item) }
            holder.itemView.setOnClickListener { onClick(item) }
        }
    }

    private fun toggle(item: MediaItem, position: Int) {
        if (!selected.add(item.path)) selected.remove(item.path)
        notifyItemChanged(position)
        onSelectionChanged(selected.size)
    }

    override fun getItemCount() = items.size

    fun update(newItems: List<MediaItem>) {
        items = newItems
        // Ne garde en sélection que les éléments encore présents.
        selected.retainAll(newItems.map { it.path }.toSet())
        notifyDataSetChanged()
        if (selectionMode) onSelectionChanged(selected.size)
    }

    // ---- API de sélection utilisée par l'activité ----

    fun setSelectionMode(on: Boolean) {
        selectionMode = on
        if (!on) selected.clear()
        notifyDataSetChanged()
        onSelectionChanged(selected.size)
    }

    fun isSelectionMode() = selectionMode

    fun selectAll() {
        selected.clear()
        items.forEach { selected.add(it.path) }
        notifyDataSetChanged()
        onSelectionChanged(selected.size)
    }

    fun selectedPaths(): List<String> = selected.toList()
}
