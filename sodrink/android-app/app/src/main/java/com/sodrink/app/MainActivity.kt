package com.sodrink.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.viewModels
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import com.sodrink.app.network.EventDto
import com.sodrink.app.ui.SoDrinkViewModel
import com.sodrink.app.ui.UiState
import com.sodrink.app.ui.theme.SoDrinkTheme

class MainActivity : ComponentActivity() {
    private val viewModel: SoDrinkViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            SoDrinkTheme {
                val state by viewModel.uiState.collectAsState()
                SoDrinkScreen(
                    state = state,
                    onBaseUrlChange = viewModel::updateBaseUrl,
                    onCredentialsChange = viewModel::updateCredentials,
                    onLogin = viewModel::login,
                    onRefresh = viewModel::refreshEvents,
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SoDrinkScreen(
    state: UiState,
    onBaseUrlChange: (String) -> Unit,
    onCredentialsChange: (String, String, Boolean) -> Unit,
    onLogin: () -> Unit,
    onRefresh: () -> Unit,
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(text = "SoDrink Mobile") },
                actions = {
                    if (state.user != null) {
                        IconButton(onClick = onRefresh) {
                            Icon(imageVector = Icons.Default.Refresh, contentDescription = "Rafraîchir")
                        }
                    }
                },
            )
        },
    ) { padding ->
        Column(
            modifier = Modifier
                .padding(padding)
                .padding(horizontal = 16.dp)
                .fillMaxSize()
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            ConnectionCard(state, onBaseUrlChange, onCredentialsChange, onLogin)

            if (state.loading) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.Center,
                ) {
                    CircularProgressIndicator()
                }
            }

            state.error?.let { err ->
                Text(
                    text = err,
                    color = MaterialTheme.colorScheme.error,
                    style = MaterialTheme.typography.bodyMedium,
                )
            }

            state.user?.let { user ->
                UserSummary(user = user)
            }

            state.nextEvent?.let { event ->
                EventCard(title = "Prochaine soirée", event = event)
            }

            if (state.upcoming.isNotEmpty()) {
                Text(
                    text = "À venir",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                )
                state.upcoming.forEach { ev ->
                    EventCard(title = null, event = ev)
                }
            }
        }
    }
}

@Composable
private fun ConnectionCard(
    state: UiState,
    onBaseUrlChange: (String) -> Unit,
    onCredentialsChange: (String, String, Boolean) -> Unit,
    onLogin: () -> Unit,
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
    ) {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(text = "Connexion", style = MaterialTheme.typography.titleMedium)
            OutlinedTextField(
                value = state.baseUrl,
                onValueChange = onBaseUrlChange,
                label = { Text("URL du serveur (sans /public)") },
                modifier = Modifier.fillMaxWidth(),
            )
            OutlinedTextField(
                value = state.pseudo,
                onValueChange = { onCredentialsChange(it, state.password, state.rememberMe) },
                label = { Text("Pseudo") },
                modifier = Modifier.fillMaxWidth(),
            )
            OutlinedTextField(
                value = state.password,
                onValueChange = { onCredentialsChange(state.pseudo, it, state.rememberMe) },
                label = { Text("Mot de passe") },
                visualTransformation = PasswordVisualTransformation(),
                modifier = Modifier.fillMaxWidth(),
            )
            Row(verticalAlignment = Alignment.CenterVertically) {
                Switch(checked = state.rememberMe, onCheckedChange = { onCredentialsChange(state.pseudo, state.password, it) })
                Spacer(modifier = Modifier.width(8.dp))
                Text(text = "Se souvenir de moi")
            }
            Button(
                onClick = onLogin,
                enabled = !state.loading,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(text = "Se connecter")
            }
        }
    }
}

@Composable
private fun UserSummary(user: com.sodrink.app.network.UserDto) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer),
    ) {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(text = "Connecté en tant que ${user.pseudo}", style = MaterialTheme.typography.titleMedium)
            Text(text = "Rôle : ${user.role}", style = MaterialTheme.typography.bodyMedium)
            user.avatar?.let { url ->
                Text(text = "Avatar : $url", style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}

@Composable
private fun EventCard(title: String?, event: EventDto) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
    ) {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            title?.let {
                Text(text = it, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            }
            event.date?.let { Text(text = "Date : $it") }
            event.theme?.let { Text(text = "Thème : $it") }
            event.lieu?.let { Text(text = "Lieu : $it") }
            event.description?.takeIf { it.isNotBlank() }?.let { Text(text = it) }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                event.author?.let { Text(text = "Auteur : ${it.pseudo}") }
                event.participantsCount?.let { Text(text = "Participants : $it") }
                event.maxParticipants?.let { Text(text = "Places max : $it") }
            }
        }
    }
}
