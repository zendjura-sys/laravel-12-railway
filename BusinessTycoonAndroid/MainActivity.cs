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

        public GameEngine Engine { get; private set; }
        Shell shell;
        Handler handler;
        bool running;
        long lastMs;
        double tickAcc, uiAcc, saveAcc;

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

            handler.PostDelayed(Loop, 33);
        }

        public void Save()
        {
            if (Engine == null) return;
            Engine.S.lastSeenUnix = Now;
            SaveStore.Save(this, Engine.S);
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
