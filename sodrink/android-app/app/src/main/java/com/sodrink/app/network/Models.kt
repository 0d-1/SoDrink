package com.sodrink.app.network

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class ApiEnvelope<T>(
    val success: Boolean,
    val data: T? = null,
    val error: String? = null,
)

@Serializable
data class CsrfResponse(
    @SerialName("csrf_token") val csrfToken: String,
)

@Serializable
data class LoginRequest(
    val pseudo: String,
    @SerialName("password") val password: String,
    val remember: Boolean = true,
    @SerialName("csrf_token") val csrfToken: String,
)

@Serializable
data class UserDto(
    val id: Int,
    val pseudo: String,
    val role: String = "user",
    val avatar: String? = null,
)

@Serializable
data class NextEventsResponse(
    val next: EventDto? = null,
    val upcoming: List<EventDto> = emptyList(),
)

@Serializable
data class EventDto(
    val id: Int? = null,
    val date: String? = null,
    val lieu: String? = null,
    val theme: String? = null,
    val description: String? = null,
    @SerialName("max_participants") val maxParticipants: Int? = null,
    val author: EventAuthor? = null,
    @SerialName("participants_count") val participantsCount: Int? = null,
)

@Serializable
data class EventAuthor(
    val id: Int,
    val pseudo: String,
)
