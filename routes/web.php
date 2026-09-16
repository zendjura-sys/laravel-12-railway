<?php

use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ThemeAssetController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Только реальные цифры — модулей Reports/Progression ещё нет,
    // поэтому XP/рейтинги/звіти на главной пока не показываем вообще,
    // а не подставляем нули или выдуманные значения.
    return Inertia::render('Home', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'memberCount' => User::query()->count(),
        'telegramBotUrl' => Setting::get('telegram_bot_url') ?: config('services.telegram.bot_url', '#'),
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Статика активной темы (Design-пакета) — публично, без auth.
Route::get('/theme-assets/{path}', [ThemeAssetController::class, 'show'])
    ->where('path', '.*')
    ->name('theme.asset');

Route::middleware(['auth', 'verified', 'permission:addons.manage|reports.manage|progression.manage|settings.manage|roles.manage|users.manage'])
    ->get('/admin', [DashboardController::class, 'index'])
    ->name('admin.dashboard');

Route::middleware(['auth', 'verified', 'permission:addons.manage'])
    ->prefix('admin/addons')
    ->name('admin.addons.')
    ->group(function () {
        Route::get('/', [AddonController::class, 'index'])->name('index');
        Route::post('/{type}/upload', [AddonController::class, 'upload'])
            ->where('type', 'core|module|plugin|theme')
            ->name('upload');
        Route::post('/{addon}/activate', [AddonController::class, 'activate'])->name('activate');
        Route::post('/{addon}/deactivate', [AddonController::class, 'deactivate'])->name('deactivate');
        Route::post('/{addon}/migrate', [AddonController::class, 'migrate'])->name('migrate');
        Route::delete('/{addon}', [AddonController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'verified', 'permission:settings.manage'])
    ->prefix('admin/settings')
    ->name('admin.settings.')
    ->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::put('/{group}', [SettingsController::class, 'update'])
            ->where('group', 'general|telegram|discord')
            ->name('update');
    });

Route::middleware(['auth', 'verified', 'permission:roles.manage'])
    ->prefix('admin/roles')
    ->name('admin.roles.')
    ->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'verified', 'permission:users.manage'])
    ->prefix('admin/users')
    ->name('admin.users.')
    ->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::put('/{user}/roles', [UserController::class, 'updateRoles'])->name('roles');
    });

// Rota que o Base44 vai acessar
Route::any('/api/nfe/emitir', function (Request $request) {
    try {
        $autoload = base_path('vendor/autoload.php');
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (!class_exists('NFePHP\NFe\Tools')) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'A biblioteca sped-nfe nao foi instalada no servidor.'
            ], 200);
        }

        $certificadoBase64 = $request->input('certificado_base64');
        $senhaCertificado = $request->input('senha_certificado');
        $xmlRecebido = $request->input('xml_nota');

        if (!$certificadoBase64 || !$senhaCertificado || !$xmlRecebido) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Aguardando dados do Base44. Teste de rota OK!'
            ], 200);
        }

        $configJson = json_encode([
            "atualizacao" => date('Y-m-d H:i:s'),
            "tpAmb" => 2,
            "razaosocial" => $request->input('razao_social', 'EMITENTE'),
            "siglaUF" => $request->input('uf', 'SP'),
            "cnpj" => preg_replace('/[^0-9]/', '', $request->input('cnpj_emitente')),
            "schemes" => "PL_009_V4",
            "versao" => "4.00",
        ]);

        $certificado = \NFePHP\Common\Certificate::readPfx(base64_decode($certificadoBase64), $senhaCertificado);
        $tools = new \NFePHP\NFe\Tools($configJson, $certificado);

        $xmlAssinado = $tools->signNFe($xmlRecebido);
        $respostaSefaz = $tools->sefazEnviaLote([$xmlAssinado], 1);

        return response()->json(['status' => 'sucesso', 'retorno' => $respostaSefaz], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'erro',
            'mensagem' => 'ERRO NO PHP: ' . $e->getMessage() . ' na linha ' . $e->getLine()
        ], 200);
    }
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
