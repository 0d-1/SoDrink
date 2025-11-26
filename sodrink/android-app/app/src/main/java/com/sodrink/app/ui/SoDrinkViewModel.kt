package com.sodrink.app.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sodrink.app.network.EventDto
import com.sodrink.app.network.SoDrinkRepository
import com.sodrink.app.network.UserDto
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class SoDrinkViewModel(
    private val repository: SoDrinkRepository = SoDrinkRepository(),
) : ViewModel() {

    private val _uiState = MutableStateFlow(UiState())
    val uiState: StateFlow<UiState> = _uiState.asStateFlow()

    fun updateBaseUrl(value: String) {
        _uiState.value = _uiState.value.copy(baseUrl = value)
    }

    fun updateCredentials(pseudo: String, password: String, remember: Boolean) {
        _uiState.value = _uiState.value.copy(pseudo = pseudo, password = password, rememberMe = remember)
    }

    fun login() {
        val state = _uiState.value
        if (state.pseudo.isBlank() || state.password.isBlank()) {
            _uiState.value = state.copy(error = "Pseudo et mot de passe sont requis")
            return
        }

        _uiState.value = state.copy(loading = true, error = null)
        viewModelScope.launch {
            try {
                val user = repository.login(
                    baseUrl = state.baseUrl,
                    pseudo = state.pseudo.trim(),
                    password = state.password,
                    remember = state.rememberMe,
                )
                val events = repository.loadNextEvents(state.baseUrl)
                _uiState.value = _uiState.value.copy(
                    user = user,
                    nextEvent = events.next,
                    upcoming = events.upcoming,
                    loading = false,
                    error = null,
                )
            } catch (t: Throwable) {
                _uiState.value = _uiState.value.copy(loading = false, error = t.message ?: "Erreur inconnue")
            }
        }
    }

    fun refreshEvents() {
        val state = _uiState.value
        if (state.user == null) return
        _uiState.value = state.copy(loading = true, error = null)
        viewModelScope.launch {
            try {
                val events = repository.loadNextEvents(state.baseUrl)
                _uiState.value = _uiState.value.copy(
                    nextEvent = events.next,
                    upcoming = events.upcoming,
                    loading = false,
                )
            } catch (t: Throwable) {
                _uiState.value = _uiState.value.copy(loading = false, error = t.message ?: "Impossible de récupérer les soirées")
            }
        }
    }
}

data class UiState(
    val baseUrl: String = "http://10.0.2.2:8000",
    val pseudo: String = "",
    val password: String = "",
    val rememberMe: Boolean = true,
    val user: UserDto? = null,
    val nextEvent: EventDto? = null,
    val upcoming: List<EventDto> = emptyList(),
    val loading: Boolean = false,
    val error: String? = null,
)
