<meta name="wemx-appearance" content="{{ json_encode([
    'mode' => settings('appearance_mode', 'auto'),
    'accent' => settings('appearance_accent', 'vaded'),
    'seasonal' => settings('appearance_seasonal', 'auto'),
    'motion' => (bool) settings('appearance_motion', true),
    'logoMotion' => (bool) settings('appearance_logo_motion', true),
]) }}">
