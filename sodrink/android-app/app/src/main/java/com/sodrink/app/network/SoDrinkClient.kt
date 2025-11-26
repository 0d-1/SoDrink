package com.sodrink.app.network

import kotlinx.serialization.json.Json
import okhttp3.JavaNetCookieJar
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.net.CookieManager
import java.net.CookiePolicy
import java.util.concurrent.TimeUnit
import okhttp3.MediaType.Companion.toMediaType

class SoDrinkClient {
    private val json = Json { ignoreUnknownKeys = true }
    private val cookieManager = CookieManager().apply { setCookiePolicy(CookiePolicy.ACCEPT_ALL) }

    private var cachedBaseUrl: String? = null
    private var cachedApi: SoDrinkApi? = null

    fun api(baseUrl: String): SoDrinkApi {
        val normalized = normalizeBaseUrl(baseUrl)
        if (normalized == cachedBaseUrl && cachedApi != null) {
            return cachedApi as SoDrinkApi
        }

        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BASIC
        }

        val client = OkHttpClient.Builder()
            .connectTimeout(20, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .cookieJar(JavaNetCookieJar(cookieManager))
            .addInterceptor(logging)
            .build()

        val contentType = "application/json".toMediaType()
        val retrofit = Retrofit.Builder()
            .baseUrl(normalized)
            .addConverterFactory(json.asConverterFactory(contentType))
            .client(client)
            .build()

        cachedBaseUrl = normalized
        cachedApi = retrofit.create(SoDrinkApi::class.java)
        return cachedApi as SoDrinkApi
    }

    private fun normalizeBaseUrl(raw: String): String {
        val trimmed = raw.trim().trimEnd('/')
        val withPublic = if (trimmed.endsWith("/public")) trimmed else "$trimmed/public"
        return "$withPublic/"
    }
}
