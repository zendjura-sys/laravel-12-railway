using System;
using Android.Graphics;
using Android.Views;
using Android.Widget;

namespace Tycoon.Droid
{
    // Базовый класс вкладки: прокручиваемый контент внутри «окна»
    public abstract class GameScreen
    {
        public readonly Shell UI;
        public ScrollView Root;
        protected LinearLayout Content;
        string builtSig;

        protected GameScreen(Shell ui) { UI = ui; }

        protected GameEngine E { get { return UI.Engine; } }
        protected GameState S { get { return UI.Engine.S; } }

        public abstract string Title { get; }

        public void Create(FrameLayout parent)
        {
            Root = new ScrollView(Ui.Ctx) { FillViewport = true, VerticalScrollBarEnabled = false };
            Content = Ui.Box(Orientation.Vertical, 10);
            int p = Ui.Dp(12);
            Content.SetPadding(p, p, p, Ui.Dp(24));
            Root.AddView(Content, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.WrapContent));
            Ui.Add(parent, Root, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.MatchParent);
            Ui.Show(Root, false);
        }

        public void Show(bool on)
        {
            Ui.Show(Root, on);
            if (on) Refresh();
        }

        public void ForceRebuild() { builtSig = null; }

        public void Refresh()
        {
            var sig = Signature();
            if (sig != builtSig)
            {
                builtSig = sig;
                Content.RemoveAllViews();
                Build();
            }
            Sync();
        }

        public void ScrollTop() { Root.ScrollTo(0, 0); }

        // Структура экрана: при её изменении экран пересобирается
        protected virtual string Signature() { return ""; }
        protected abstract void Build();
        // Обновление значений без пересборки
        protected abstract void Sync();

        protected LinearLayout Cell() { return Ui.Card(Content); }

        // «$1.2K + 12 Древесина»; нехватка подсвечивается красным
        protected Rich Cost(Rich r, UpgradeCost c)
        {
            r.C(Fmt.Money(c.Money), S.money >= c.Money ? Pal.Text : Pal.Red);
            r.T(" + ");
            r.C(Fmt.Num(c.Mat.Count) + " " + GameData.Resources[c.Mat.Res].Name, E.HasMat(c.Mat) ? Pal.Text : Pal.Red);
            return r;
        }
    }

    public class Shell
    {
        readonly MainActivity activity;
        public GameEngine Engine { get { return activity.Engine; } }
        public MainActivity Activity { get { return activity; } }

        public FrameLayout Root;
        FrameLayout overlay;
        TextView money, income, worth, windowTitle;
        Btn[] tabs;
        GameScreen[] screens;
        int current = -1;
        View modal;

        static readonly string[] TabNames = { "Главная", "Бизнес", "Склад", "Биржа", "Компании", "Банк" };
        static readonly string[] TabIcons = { "🏠", "🏭", "📦", "📈", "🚀", "🏦" };

        public Shell(MainActivity a) { activity = a; }

        public void Build()
        {
            Root = new FrameLayout(Ui.Ctx);
            Root.SetBackgroundColor(Pal.Bg);

            var main = Ui.Box(Orientation.Vertical, 8);
            int m = Ui.Dp(10);
            main.SetPadding(m, m, m, m);
            Ui.Add(Root, main, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.MatchParent);

            BuildTopBar(main);
            BuildWindow(main);
            BuildTabs(main);

            overlay = new FrameLayout(Ui.Ctx);
            Ui.Add(Root, overlay, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.MatchParent);

            SwitchTab(0);
        }

        // Верхняя панель: монета, деньги, доход, капитал
        void BuildTopBar(LinearLayout parent)
        {
            var bar = Ui.Box(Orientation.Horizontal, 12);
            bar.Background = Ui.Round(Pal.Panel, 14);
            bar.SetGravity(GravityFlags.CenterVertical);
            int p = Ui.Dp(12);
            bar.SetPadding(p, p, p, p);
            Ui.Add(parent, bar, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.WrapContent);

            var coin = Ui.Label(null, "$", 26, Pal.GoldLip, true, GravityFlags.Center);
            coin.Background = Ui.Circle(Pal.Gold);
            coin.SetIncludeFontPadding(false);
            Ui.Add(bar, coin, Ui.Dp(52), Ui.Dp(52));

            var stack = Ui.Stack(bar);
            money = Ui.Label(stack, "$0", 28, Pal.Gold, true);
            money.SetShadowLayer(2, 0, Ui.Dp(1.5f), Color.Argb(90, 0, 0, 0));
            money.SetSingleLine(true);
            var row = Ui.Box(Orientation.Horizontal, 8);
            Ui.Add(stack, row, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.WrapContent);
            income = Ui.Label(null, "", 14, Pal.Green, true);
            Ui.Add(row, income, 0, ViewGroup.LayoutParams.WrapContent, 1);
            worth = Ui.Label(null, "", 13, Pal.Muted, false, GravityFlags.End | GravityFlags.CenterVertical);
            Ui.Add(row, worth, ViewGroup.LayoutParams.WrapContent, ViewGroup.LayoutParams.WrapContent);
        }

        // Центральное «окно» с шапкой, в котором живут вкладки
        void BuildWindow(LinearLayout parent)
        {
            var win = Ui.Box(Orientation.Vertical, 0);
            win.Background = Ui.Round(Pal.Panel, 14);
            Ui.Add(parent, win, ViewGroup.LayoutParams.MatchParent, 0, 1);

            windowTitle = Ui.Header(win, "");

            var area = new FrameLayout(Ui.Ctx);
            Ui.Add(win, area, ViewGroup.LayoutParams.MatchParent, 0, 1);

            screens = new GameScreen[]
            {
                new HomeScreen(this),
                new BusinessScreen(this),
                new WarehouseScreen(this),
                new MarketScreen(this),
                new CompanyScreen(this),
                new BankScreen(this),
            };
            foreach (var s in screens) s.Create(area);
        }

        // Нижняя панель вкладок; активная — зелёная, как TAB 1 в референсе
        void BuildTabs(LinearLayout parent)
        {
            var bar = Ui.Box(Orientation.Horizontal, 5);
            bar.Background = Ui.Round(Pal.Header, 14);
            int p = Ui.Dp(6);
            bar.SetPadding(p, p, p, p);
            Ui.Add(parent, bar, ViewGroup.LayoutParams.MatchParent, Ui.Dp(72));

            tabs = new Btn[TabNames.Length];
            for (int i = 0; i < TabNames.Length; i++)
            {
                int idx = i;
                tabs[i] = Ui.Button(bar, TabIcons[i] + "\n" + TabNames[i], Pal.Btn, () => SwitchTab(idx), 12, 0);
            }
        }

        public void SwitchTab(int i)
        {
            if (i == current) { screens[i].ScrollTop(); return; }
            if (current >= 0) screens[current].Show(false);
            current = i;
            for (int t = 0; t < tabs.Length; t++) tabs[t].SetColor(t == i ? Pal.Green : Pal.Btn);
            windowTitle.Text = screens[i].Title.ToUpperInvariant();
            screens[i].Show(true);
        }

        public void Refresh()
        {
            var e = Engine;
            Ui.Set(money, Fmt.Money(e.S.money));
            Ui.Set(income, "+" + Fmt.Money(e.IncomePerSec) + " / сек");
            Ui.Set(worth, "Капитал " + Fmt.Money(e.NetWorth()));

            if (e.StructureDirty)
            {
                e.StructureDirty = false;
                foreach (var s in screens) s.ForceRebuild();
            }
            if (current >= 0) screens[current].Refresh();
        }

        public void Toast(string text)
        {
            Android.Widget.Toast.MakeText(Ui.Ctx, text, ToastLength.Short).Show();
        }

        // Выполняет действие; при неудаче показывает подсказку и сразу обновляет экран
        public void Try(bool ok, string failText)
        {
            if (!ok) Toast(failText);
            Refresh();
        }

        // true — нажатие «Назад» обработано внутри игры
        public bool OnBack()
        {
            if (modal != null) { CloseModal(); return true; }
            if (current != 0) { SwitchTab(0); return true; }
            return false;
        }

        // Всплывающая надпись «+$X» в точке нажатия (координаты — в окне)
        public void FloatText(float winX, float winY, string text)
        {
            var t = Ui.Label(null, text, 22, Pal.Gold, true, GravityFlags.Center);
            t.SetShadowLayer(3, 0, Ui.Dp(1), Color.Argb(160, 0, 0, 0));
            var loc = new int[2];
            overlay.GetLocationInWindow(loc);
            var lp = new FrameLayout.LayoutParams(Ui.Dp(200), Ui.Dp(40));
            lp.LeftMargin = (int)(winX - loc[0]) - Ui.Dp(100) + new Random().Next(-Ui.Dp(20), Ui.Dp(20));
            lp.TopMargin = (int)(winY - loc[1]) - Ui.Dp(40);
            overlay.AddView(t, lp);
            t.Animate().TranslationYBy(-Ui.Dp(90)).Alpha(0).SetDuration(850)
                .WithEndAction(new Java.Lang.Runnable(() => overlay.RemoveView(t))).Start();
        }

        // ----- Модальные окна в стиле «INFO» -----
        public void ShowModal(string title, Rich text, string okText = "OK", Action onOk = null,
                              string cancelText = null, Color? okColor = null)
        {
            CloseModal();
            var dim = new FrameLayout(Ui.Ctx) { Clickable = true };
            dim.SetBackgroundColor(Color.Argb(170, 0, 0, 0));
            modal = dim;
            Ui.Add(overlay, dim, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.MatchParent);

            var win = Ui.Box(Orientation.Vertical, 12);
            win.Background = Ui.Round(Pal.Panel, 16);
            win.SetPadding(0, 0, 0, Ui.Dp(16));
            var lp = new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.WrapContent,
                GravityFlags.Center);
            lp.LeftMargin = lp.RightMargin = Ui.Dp(20);
            dim.AddView(win, lp);

            Ui.Header(win, title);
            var inner = Ui.Box(Orientation.Vertical, 14);
            int p = Ui.Dp(14);
            inner.SetPadding(p, 0, p, 0);
            Ui.Add(win, inner, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.WrapContent);

            var body = Ui.Card(inner);
            var t = Ui.Label(body, "", 16, Pal.Text);
            Ui.Set(t, text);

            var row = Ui.Row(inner, 54, 12);
            if (cancelText != null) Ui.Button(row, cancelText, Pal.Blue, CloseModal, 16, 0);
            Ui.Button(row, okText, okColor ?? Pal.Green, () => { CloseModal(); if (onOk != null) onOk(); }, 16, 0);
        }

        public void ShowModal(string title, string text, string okText = "OK", Action onOk = null,
                              string cancelText = null, Color? okColor = null)
        {
            ShowModal(title, new Rich().T(text), okText, onOk, cancelText, okColor);
        }

        public void CloseModal()
        {
            if (modal == null) return;
            overlay.RemoveView(modal);
            modal = null;
        }

        public void ShowOfflineReport(OfflineReport rep)
        {
            var r = new Rich();
            r.T("Вас не было: " + Fmt.Time(rep.Seconds)).N();
            if (rep.Seconds >= GameData.OfflineCapSeconds) r.C("(учитывается максимум 8 часов)", Pal.Muted).N();
            r.N().T("Заработано: ").B(Fmt.Money(rep.Money), Pal.Gold);
            for (int i = 0; i < rep.Res.Length; i++)
            {
                if (rep.Res[i] < 0.5) continue;
                var d = GameData.Resources[i];
                r.N().T(Icons.Of(d.Id) + " " + d.Name + ": +" + Fmt.Num(rep.Res[i]));
            }
            ShowModal("С возвращением!", r, "Отлично");
        }
    }
}
