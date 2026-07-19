package com.example.photosync

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import coil.load

/** Filtre d'affichage de la galerie : tout, photos seules, ou vidéos seules. */
enum class MediaFilter { ALL, IMAGES, VIDEOS }

/** Grille des médias du serveur (miniatures chargées via Coil), filtrable photos/vidéos. */
class PhotoAdapter(
    private val thumbUrl: (Long) -> String,
    private val onClick: (ServerPhoto) -> Unit,
) : RecyclerView.Adapter<PhotoAdapter.VH>() {

    // `all` = tout ce qui a été chargé ; `visible` = ce qui passe le filtre courant.
    private val all = mutableListOf<ServerPhoto>()
    private val visible = mutableListOf<ServerPhoto>()
    private var filter = MediaFilter.ALL
    private var sourceFilter: String? = null // null = toutes origines ; sinon 'phone'/'computer'/'web'

    fun addAll(list: List<ServerPhoto>) {
        all.addAll(list)
        applyFilter()
    }

    fun clear() {
        all.clear()
        visible.clear()
        notifyDataSetChanged()
    }

    /** Change le filtre photos/vidéos et rafraîchit l'affichage. */
    fun setFilter(f: MediaFilter) {
        if (f == filter) return
        filter = f
        applyFilter()
    }

    /** Change le filtre par origine (null = toutes) et rafraîchit l'affichage. */
    fun setSourceFilter(s: String?) {
        if (s == sourceFilter) return
        sourceFilter = s
        applyFilter()
    }

    /** Nombre d'éléments réellement affichés (après filtre). */
    fun visibleCount(): Int = visible.size

    private fun applyFilter() {
        visible.clear()
        all.filterTo(visible) { p ->
            val mediaOk = when (filter) {
                MediaFilter.ALL    -> true
                MediaFilter.IMAGES -> !p.isVideo
                MediaFilter.VIDEOS -> p.isVideo
            }
            val srcOk = sourceFilter == null || p.source.equals(sourceFilter, ignoreCase = true)
            mediaOk && srcOk
        }
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val v = LayoutInflater.from(parent.context).inflate(R.layout.item_photo, parent, false)
        return VH(v)
    }

    override fun getItemCount() = visible.size

    override fun onBindViewHolder(holder: VH, position: Int) {
        val photo = visible[position]
        holder.image.load(thumbUrl(photo.id)) { crossfade(true) }
        holder.playBadge.visibility = if (photo.isVideo) View.VISIBLE else View.GONE
        holder.dateText.text = formatDate(photo.date)
        // Origine : cadre + pastille de couleur différente selon téléphone / ordinateur / web.
        when (photo.source.lowercase()) {
            "computer" -> { holder.itemView.setBackgroundColor(COLOR_COMPUTER); holder.sourceBadge.text = "💻" }
            "web"      -> { holder.itemView.setBackgroundColor(COLOR_WEB);      holder.sourceBadge.text = "🌐" }
            else       -> { holder.itemView.setBackgroundColor(COLOR_PHONE);    holder.sourceBadge.text = "📱" }
        }
        holder.itemView.setOnClickListener { onClick(photo) }
    }

    /** Met la date serveur (« 2026-06-25 10:05:00 » ou « 2026-06-25 ») au format JJ/MM/AAAA. */
    private fun formatDate(raw: String): String {
        if (raw.isBlank()) return ""
        val parts = raw.take(10).split("-")
        return if (parts.size == 3) "${parts[2]}/${parts[1]}/${parts[0]}" else raw.take(10)
    }

    class VH(view: View) : RecyclerView.ViewHolder(view) {
        val image: ImageView = view.findViewById(R.id.photo)
        val playBadge: ImageView = view.findViewById(R.id.playBadge)
        val dateText: TextView = view.findViewById(R.id.dateText)
        val sourceBadge: TextView = view.findViewById(R.id.sourceBadge)
    }

    companion object {
        private const val COLOR_PHONE    = 0xFF1565C0.toInt() // bleu : envoyé depuis le téléphone
        private const val COLOR_COMPUTER = 0xFF16A34A.toInt() // vert : envoyé depuis l'ordinateur
        private const val COLOR_WEB      = 0xFFE8772E.toInt() // orange : ajouté depuis le web
    }
}
