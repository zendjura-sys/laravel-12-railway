<?php

use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeployController;
use App\Http\Controllers\Admin\DesignController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use App\Models\User;
use App\Support\FamilyContent;
use App\Support\TelegramLink;
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
        'telegramBotUrl' => TelegramLink::url(),
        // Содержание идёт через FamilyContent: правки из админки, а при их
        // отсутствии — значения из config/family.php. Тот же источник читает
        // бот в Telegram. Пока список жил во Vue, правка должностей означала
        // расхождение: на сайте одно, в боте другое.
        'positions' => FamilyContent::positions(),
        'baseCount' => FamilyContent::baseCount(),
        'directions' => FamilyContent::directions(),
        'promotionCriteria' => FamilyContent::promotionCriteria(),
        'leadership' => FamilyContent::leadership(),
    ]);
})->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard', [
        'memberCount' => User::query()->count(),
        'telegramBotUrl' => TelegramLink::url(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'verified', 'permission:addons.manage|reports.manage|progression.manage|settings.manage|roles.manage|users.manage|members.manage|goals.manage|broadcasts.manage|telegram.manage'])
    ->get('/admin', [DashboardController::class, 'index'])
    ->name('admin.dashboard');

Route::middleware(['auth', 'verified', 'permission:addons.manage'])
    ->prefix('admin/addons')
    ->name('admin.addons.')
    ->group(function () {
        Route::get('/', [AddonController::class, 'index'])->name('index');
        Route::post('/{type}/upload', [AddonController::class, 'upload'])
            ->where('type', 'core|module|plugin')
            ->name('upload');
        Route::post('/{addon}/activate', [AddonController::class, 'activate'])->name('activate');
        Route::post('/{addon}/deactivate', [AddonController::class, 'deactivate'])->name('deactivate');
        Route::post('/{addon}/migrate', [AddonController::class, 'migrate'])->name('migrate');
        Route::delete('/{addon}', [AddonController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'verified', 'permission:addons.manage'])
    ->prefix('admin/deploy')
    ->name('admin.deploy.')
    ->group(function () {
        Route::post('/trigger', [DeployController::class, 'trigger'])->name('trigger');
        Route::get('/status', [DeployController::class, 'status'])->name('status');
    });

// Дизайн — обычный раздел настроек, а не аддон: см. DesignController.
Route::middleware(['auth', 'verified', 'permission:settings.manage'])
    ->prefix('admin/design')
    ->name('admin.design.')
    ->group(function () {
        Route::get('/', [DesignController::class, 'index'])->name('index');
        Route::post('/brand', [DesignController::class, 'updateBrand'])->name('brand');
        Route::put('/theme', [DesignController::class, 'updateTheme'])->name('theme');
        Route::put('/content', [DesignController::class, 'updateContent'])->name('content');
        Route::post('/content/reset', [DesignController::class, 'resetContent'])->name('content.reset');
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
        Route::put('/{user}/position', [UserController::class, 'updatePosition'])->name('position');
    });

/*
 * Rota que o Base44 vai acessar.
 *
 * ВЫКЛЮЧЕНА ПО УМОЛЧАНИЮ. Маршрут остался от другого проекта в этом же
 * репозитории: он без авторизации и без CSRF принимает base64 PFX-
 * сертификат, пароль к нему и произвольный XML, подписывает и отправляет
 * в SEFAZ. На домене Monsory он открыт всему интернету и ничем здесь не
 * используется, поэтому висит за флагом.
 *
 * Код не удалён, чтобы не сломать тот проект: чтобы вернуть маршрут,
 * достаточно NFE_ENDPOINT_ENABLED=true в .env. Но прежде чем включать,
 * его стоит закрыть авторизацией — в нынешнем виде подписывать документы
 * через него может кто угодно.
 */
if (config('services.nfe.enabled')) {
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
        // Текст исключения и номер строки наружу не отдаём: маршрут
        // публичный, а это готовая карта внутренностей приложения.
        report($e);

        return response()->json([
            'status' => 'erro',
            'mensagem' => 'Erro interno ao processar a requisicao.'
        ], 200);
    }
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
}
