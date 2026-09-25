using System;
using Android.App;
using Android.Content.PM;
using Android.OS;
using Android.Views;
using Android.Window;

namespace Tycoon.Droid
{
    [Activity(Label = "@string/app_name", MainLauncher = true, Exported = true, Theme = "@style/AppTheme",
        ScreenOrientation = ScreenOrientation.Portrait,
        WindowSoftInputMode = SoftInput.AdjustResize,
        ConfigurationChanges = ConfigChanges.ScreenSize | ConfigChanges.Orientation | ConfigChanges.UiMode |
                               ConfigChanges.ScreenLayout | ConfigChanges.SmallestScreenSize |
                               ConfigChanges.Keyboard | ConfigChanges.KeyboardHidden)]
    public class MainActivity : Activity
    {
        const double TickStep = 0.1;
        const double UiStep = 0.2;
        const double SaveStep = 10;
        const double CloudStep = 120;

        public GameEngine Engine { get; private set; }
        public CloudService Cloud { get; private set; }
        Shell shell;
        Handler handler;
        bool running;
        long lastMs;
        double tickAcc, uiAcc, saveAcc, cloudAcc;

        static long Now { get { return DateTimeOffset.UtcNow.ToUnixTimeSeconds(); } }

        protected override void OnCreate(Bundle savedInstanceState)
        {
            base.OnCreate(savedInstanceState);
            Ui.Init(this);

            var state = SaveStore.Load(this);
            Engine = new GameEngine(state);
            if (state == null)
            {
                Engine.S.lastSeenUnix = Now;
                Engine.AddNews("Добро пожаловать! Кликайте, покупайте первую ферму и стройте империю.", 1);
            }

            Cloud = new CloudService(this);
            shell = new Shell(this);
            shell.Build();
            SetContentView(shell.Root);
            ApplyInsets();

            handler = new Handler(Looper.MainLooper);

            if (Build.VERSION.SdkInt >= BuildVersionCodes.Tiramisu)
                OnBackInvokedDispatcher.RegisterOnBackInvokedCallback(0, new BackCallback(this));
        }

        // Android 15+ рисует приложение под системными панелями — добавляем отступы
        void ApplyInsets()
        {
            shell.Root.SetOnApplyWindowInsetsListener(new InsetsListener());
            shell.Root.RequestApplyInsets();
        }

        protected override void OnResume()
        {
            base.OnResume();
            // досчитываем прогресс за время, пока игра была закрыта или свёрнута
            long away = Now - Engine.S.lastSeenUnix;
            Engine.S.lastSeenUnix = Now;
            if (away >= 10)
            {
                var rep = Engine.SimulateOffline(away);
                if (rep.Money > 0 || away > 60) shell.ShowOfflineReport(rep);
            }
            running = true;
            lastMs = SystemClock.UptimeMillis();
            shell.Refresh();
            handler.PostDelayed(Loop, 50);
        }

        protected override void OnPause()
        {
            running = false;
            Save();
            CloudSave(false);
            base.OnPause();
        }

        void Loop()
        {
            if (!running) return;
            long now = SystemClock.UptimeMillis();
            double dt = Math.Min((now - lastMs) / 1000.0, 1.0);
            lastMs = now;

            tickAcc += dt;
            while (tickAcc >= TickStep)
            {
                tickAcc -= TickStep;
                Engine.Tick(TickStep);
            }

            uiAcc += dt;
            if (uiAcc >= UiStep)
            {
                uiAcc = 0;
                shell.Refresh();
            }

            saveAcc += dt;
            if (saveAcc >= SaveStep)
            {
                saveAcc = 0;
                Save();
            }

            cloudAcc += dt;
            if (cloudAcc >= CloudStep)
            {
                cloudAcc = 0;
                CloudSave(false);
            }

            handler.PostDelayed(Loop, 33);
        }

        public void Save()
        {
            if (Engine == null) return;
            Engine.S.lastSeenUnix = Now;
            SaveStore.Save(this, Engine.S);
        }

        // ---------- Облако (Firebase) ----------
        public async void CloudSave(bool manual)
        {
            if (!Cloud.Configured || !Cloud.SignedIn || Cloud.Busy) return;
            Cloud.Busy = true;
            try
            {
                Engine.S.lastSeenUnix = Now;
                await Cloud.Upload(SaveStore.Serialize(Engine.S), Engine.NetWorth(), Engine.S.level);
                Cloud.Status = "Сохранено в облако в " + DateTime.Now.ToString("HH:mm");
                if (manual) shell.Toast("Сохранено в облако");
            }
            catch (Exception e)
            {
                Cloud.Status = "Ошибка облака: " + e.Message;
                if (manual) shell.Toast(Cloud.Status);
            }
            finally { Cloud.Busy = false; }
        }

        public async void SignIn()
        {
            if (!Cloud.Configured || Cloud.Busy) return;
            Cloud.Busy = true;
            try
            {
                await Cloud.SignIn(this);
                shell.Toast(Cloud.Status);
            }
            catch (Exception e)
            {
                Cloud.Status = "Не удалось войти: " + e.Message;
                shell.Toast(Cloud.Status);
                return;
            }
            finally { Cloud.Busy = false; }
            // после входа сверяемся с облаком: там может быть прогресс с другого устройства
            CloudLoad(auto: true);
        }

        public async void CloudLoad(bool auto = false)
        {
            if (!Cloud.Configured || !Cloud.SignedIn || Cloud.Busy) return;
            Cloud.Busy = true;
            GameState remote;
            try
            {
                var json = await Cloud.Download();
                remote = json == null ? null : SaveStore.Parse(json);
            }
            catch (Exception e)
            {
                Cloud.Status = "Ошибка облака: " + e.Message;
                shell.Toast(Cloud.Status);
                return;
            }
            finally { Cloud.Busy = false; }

            if (remote == null)
            {
                if (!auto) shell.Toast("В облаке пока нет сохранения");
                CloudSave(false);
                return;
            }
            bool better = remote.totalEarned > Engine.S.totalEarned;
            if (auto && !better) { CloudSave(false); return; }
            var r = new Rich()
                .T("В облаке: уровень " + remote.level + ", заработано " + Fmt.Money(remote.totalEarned)).N()
                .T("На телефоне: уровень " + Engine.S.level + ", заработано " + Fmt.Money(Engine.S.totalEarned)).N().N()
                .C("Прогресс на телефоне будет заменён.", Pal.Gold);
            shell.ShowModal("Облачное сохранение", r, "Загрузить", () => ApplyState(remote), "Отмена");
        }

        void ApplyState(GameState s)
        {
            s.lastSeenUnix = Now;
            Engine.S = s;
            Engine.IncomePerSec = 0;
            Engine.StructureDirty = true;
            Save();
            shell.Toast("Прогресс загружен из облака");
        }

        public void SignOut()
        {
            Cloud.SignOut();
            shell.Toast("Вы вышли из аккаунта");
        }

        public void ResetGame()
        {
            SaveStore.Clear(this);
            Engine.S = GameState.Create();
            Engine.IncomePerSec = 0;
            Engine.S.lastSeenUnix = Now;
            Engine.StructureDirty = true;
            Save();
        }

        public void HandleBack()
        {
            if (shell.OnBack()) return;
            Save();
            MoveTaskToBack(true);
        }

        // Android 12 и старше
        public override void OnBackPressed() { HandleBack(); }

        class BackCallback : Java.Lang.Object, IOnBackInvokedCallback
        {
            readonly MainActivity a;
            public BackCallback(MainActivity a) { this.a = a; }
            public void OnBackInvoked() { a.HandleBack(); }
        }

        class InsetsListener : Java.Lang.Object, View.IOnApplyWindowInsetsListener
        {
            public WindowInsets OnApplyWindowInsets(View v, WindowInsets insets)
            {
                if (Build.VERSION.SdkInt >= BuildVersionCodes.R)
                {
                    var bars = insets.GetInsets(WindowInsets.Type.SystemBars() | WindowInsets.Type.DisplayCutout());
                    var ime = insets.GetInsets(WindowInsets.Type.Ime());
                    v.SetPadding(bars.Left, bars.Top, bars.Right, Math.Max(bars.Bottom, ime.Bottom));
                }
                else
                {
#pragma warning disable CS0618
                    v.SetPadding(insets.SystemWindowInsetLeft, insets.SystemWindowInsetTop,
                                 insets.SystemWindowInsetRight, insets.SystemWindowInsetBottom);
#pragma warning restore CS0618
                }
                return insets;
            }
        }
    }
}
