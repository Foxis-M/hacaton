<?php
// Language switcher component

// Function to set language
function setLanguage($lang) {
    $_SESSION['language'] = $lang;
}

// Function to get current language
function getCurrentLanguage() {
    return $_SESSION['language'] ?? 'ru'; // Default to Russian
}

// Function to get available languages
function getAvailableLanguages() {
    return [
        'ru' => 'Русский',
        'en' => 'English'
    ];
}

// Language switcher HTML
function renderLanguageSwitcher() {
    $currentLang = getCurrentLanguage();
    $languages = getAvailableLanguages();
    
    echo '<div class="language-switcher">';
    echo '<select onchange="changeLanguage(this.value)" class="lang-select">';
    
    foreach ($languages as $code => $name) {
        $selected = ($code === $currentLang) ? 'selected' : '';
        echo "<option value=\"$code\" $selected>$name</option>";
    }
    
    echo '</select>';
    echo '</div>';
    
    echo '<script>
    function changeLanguage(lang) {
        // Send AJAX request to update session
        fetch("/api/set-language.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({language: lang})
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                location.reload();
            }
        })
        .catch(error => console.error("Error:", error));
    }
    </script>';
}
?>