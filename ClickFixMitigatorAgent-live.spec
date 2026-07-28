# -*- mode: python ; coding: utf-8 -*-


a = Analysis(
    ['windows-agent\\agent.py'],
    pathex=[],
    binaries=[],
    datas=[('windows-agent\\config.json', '.'), ('windows-agent\\logo.png', '.'), ('windows-agent\\TERMS_AND_CONDITIONS.txt', '.'), ('windows-agent\\data', 'data')],
    hiddenimports=['winotify', 'pystray._win32'],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[],
    noarchive=False,
    optimize=0,
)
pyz = PYZ(a.pure)

exe = EXE(
    pyz,
    a.scripts,
    [],
    exclude_binaries=True,
    name='ClickFixMitigatorAgent-live',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    console=True,
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
    icon=['windows-agent\\logo.png'],
)
coll = COLLECT(
    exe,
    a.binaries,
    a.datas,
    strip=False,
    upx=True,
    upx_exclude=[],
    name='ClickFixMitigatorAgent-live',
)
