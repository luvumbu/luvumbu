plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("com.google.devtools.ksp")
}

android {
    namespace = "com.example.photosync"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.example.photosync"
        minSdk = 26
        targetSdk = 35
        versionCode = 15
        versionName = "2.4"
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
    buildFeatures { viewBinding = true }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("com.google.android.material:material:1.12.0")
    implementation("androidx.recyclerview:recyclerview:1.3.2")

    // Synchro en arrière-plan
    implementation("androidx.work:work-runtime-ktx:2.9.1")

    // Réseau (upload multipart)
    implementation("com.squareup.okhttp3:okhttp:4.12.0")

    // Chargement des images depuis le serveur (galerie en ligne)
    implementation("io.coil-kt:coil:2.7.0")

    // Suivi local des photos déjà envoyées
    implementation("androidx.room:room-runtime:2.6.1")
    implementation("androidx.room:room-ktx:2.6.1")
    ksp("androidx.room:room-compiler:2.6.1")

    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.8.1")
}
