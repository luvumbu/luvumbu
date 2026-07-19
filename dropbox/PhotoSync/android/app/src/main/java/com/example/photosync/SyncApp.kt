package com.example.photosync

import android.app.Application
import androidx.room.Room

/** Application : initialise et expose la base Room. */
class SyncApp : Application() {

    lateinit var db: AppDb
        private set

    override fun onCreate() {
        super.onCreate()
        instance = this
        db = Room.databaseBuilder(this, AppDb::class.java, "photosync.db").build()
    }

    companion object {
        lateinit var instance: SyncApp
            private set
    }
}
