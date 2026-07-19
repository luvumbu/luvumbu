package com.example.photosync

import androidx.room.Dao
import androidx.room.Database
import androidx.room.Entity
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.PrimaryKey
import androidx.room.Query
import androidx.room.RoomDatabase

/** Une photo déjà envoyée au serveur (identifiée par son id MediaStore). */
@Entity(tableName = "uploaded")
data class UploadedPhoto(
    @PrimaryKey val mediaId: Long,
    val uploadedAt: Long,
)

@Dao
interface UploadedDao {
    @Query("SELECT mediaId FROM uploaded")
    suspend fun allIds(): List<Long>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun markUploaded(photo: UploadedPhoto)

    @Query("SELECT COUNT(*) FROM uploaded")
    suspend fun count(): Int

    @Query("DELETE FROM uploaded")
    suspend fun clearAll()
}

@Database(entities = [UploadedPhoto::class], version = 1, exportSchema = false)
abstract class AppDb : RoomDatabase() {
    abstract fun uploadedDao(): UploadedDao
}
