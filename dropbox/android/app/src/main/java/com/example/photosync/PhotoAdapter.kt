package com.example.photosync

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import coil.load

/**
 * Grille des fichiers du serveur.
 *  - Photos : miniature image (taille réduite) chargée via Coil.
 *  - Vidéo / audio / document / autre : icône locale + nom du fichier
 *    (aucune image lourde n'est téléchargée pour l'aperçu).
 */
class PhotoAdapter(
    private val thumbUrl: (Long) -> String,
    private val onClick: (ServerPhoto) -> Unit,
) : RecyclerView.Adapter<PhotoAdapter.VH>() {

    private val items = mutableListOf<ServerPhoto>()

    fun addAll(list: List<ServerPhoto>) {
        val start = items.size
        items.addAll(list)
        notifyItemRangeInserted(start, list.size)
    }

    fun clear() {
        val n = items.size
        items.clear()
        notifyItemRangeRemoved(0, n)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val v = LayoutInflater.from(parent.context).inflate(R.layout.item_photo, parent, false)
        return VH(v)
    }

    override fun getItemCount() = items.size

    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]
        if (item.hasImageThumb) {
            // Vraie photo : miniature image + petit logo appareil photo.
            holder.icon.visibility = View.GONE
            holder.label.visibility = View.GONE
            holder.image.visibility = View.VISIBLE
            holder.image.load(thumbUrl(item.id)) { crossfade(true) }
            holder.typeBadge.visibility = View.VISIBLE
            holder.typeBadge.setImageResource(iconFor(item.category))
        } else {
            // Autres types : grande icône centrale (le type est déjà clair) + nom.
            holder.image.setImageDrawable(null)
            holder.image.visibility = View.GONE
            holder.icon.visibility = View.VISIBLE
            holder.icon.setImageResource(iconFor(item.category))
            holder.label.visibility = View.VISIBLE
            holder.label.text = item.name
            holder.typeBadge.visibility = View.GONE
        }
        holder.itemView.setOnClickListener { onClick(item) }
    }

    private fun iconFor(category: String): Int = when (category) {
        "photo" -> R.drawable.ic_cat_photo
        "video" -> R.drawable.ic_cat_video
        "audio" -> R.drawable.ic_cat_audio
        "document" -> R.drawable.ic_cat_document
        else -> R.drawable.ic_cat_file
    }

    class VH(view: View) : RecyclerView.ViewHolder(view) {
        val image: ImageView = view.findViewById(R.id.photo)
        val icon: ImageView = view.findViewById(R.id.icon)
        val label: TextView = view.findViewById(R.id.label)
        val typeBadge: ImageView = view.findViewById(R.id.typeBadge)
    }
}
