package com.sodrink.app.network

class SoDrinkRepository(
    private val client: SoDrinkClient = SoDrinkClient(),
) {
    suspend fun login(baseUrl: String, pseudo: String, password: String, remember: Boolean): UserDto {
        val api = client.api(baseUrl)
        val csrf = api.fetchCsrf().data?.csrfToken ?: error("CSRF manquant")

        val envelope = api.login(
            LoginRequest(
                pseudo = pseudo,
                password = password,
                remember = remember,
                csrfToken = csrf,
            ),
        )
        val user = envelope.data?.get("user")
        return user ?: error(envelope.error ?: "Réponse de connexion inconnue")
    }

    suspend fun loadNextEvents(baseUrl: String, limit: Int = 6): NextEventsResponse {
        val api = client.api(baseUrl)
        val envelope = api.getNextEvents(limit)
        return envelope.data ?: NextEventsResponse()
    }
}
