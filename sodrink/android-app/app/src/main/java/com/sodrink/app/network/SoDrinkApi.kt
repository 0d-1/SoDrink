package com.sodrink.app.network

import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Query

interface SoDrinkApi {
    @GET("api/csrf.php")
    suspend fun fetchCsrf(): ApiEnvelope<CsrfResponse>

    @POST("api/auth/login.php")
    suspend fun login(@Body request: LoginRequest): ApiEnvelope<Map<String, UserDto>>

    @GET("api/sections/next-event.php")
    suspend fun getNextEvents(@Query("limit") limit: Int = 6): ApiEnvelope<NextEventsResponse>
}
