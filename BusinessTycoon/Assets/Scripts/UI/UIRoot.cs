using System;
using UnityEngine;
using UnityEngine.EventSystems;
using UnityEngine.UI;

namespace Tycoon.UI
{
    // Базовый класс экрана-вкладки: скролл-контент внутри «окна»
    public abstract class GameScreen
    {
        public readonly UIRoot UI;
        public RectTransform Root;
        protected RectTransform Content;
        ScrollRect scroll;
        string builtSig;

        protected GameScreen(UIRoot ui) { UI = ui; }

        protected GameEngine E { get { return UI.Engine; } }
        protected GameState S { get { return UI.Engine.S; } }

        public abstract string Title { get; }

        public void Create(Transform parent)
        {
            Root = UIKit.Rect(parent, GetType().Name);
            UIKit.Stretch(Root);
            Content = UIKit.Scroll(Root, out scroll);
        }

        public void Show(bool on)
        {
            UIKit.SetActive(Root, on);
            if (on) Refresh();
        }

        public void ForceRebuild() { builtSig = null; }

        public void Refresh()
        {
            var sig = Signature();
            if (sig != builtSig)
            {
                builtSig = sig;
                UIKit.Clear(Content);
                Build();
            }
            Sync();
        }

        public void ScrollTop() { scroll.verticalNormalizedPosition = 1; }

        // Структура экрана: при её изменении экран пересобирается
        protected virtual string Signature() { return ""; }
        protected abstract void Build();
        // Обновление значений без пересборки
        protected abstract void Sync();

        // ----- Общие помощники для экранов -----
        protected RectTransform Cell(Transform parent = null) { return UIKit.Card(parent ?? Content, Pal.Card, 24, 12); }

        protected Text Subtitle(Transform parent, string s)
        {
            return UIKit.Label(parent, s, 36, Pal.Text, TextAnchor.MiddleLeft, FontStyle.Bold);
        }

        protected Text Body(Transform parent, string s = "", int size = 30)
        {
            return UIKit.Label(parent, s, size, Pal.Muted, TextAnchor.UpperLeft);
        }

        protected RectTransform VStack(Transform parent, float spacing = 4)
        {
            var rt = UIKit.Rect(parent, "Stack");
            UIKit.VList(rt, spacing, 0).childAlignment = TextAnchor.MiddleLeft;
            UIKit.LE(rt, -1, 1);
            return rt;
        }

        // Строка вида «$1.2K + 12 Древесина»; нехватка подсвечивается красным
        protected string CostText(UpgradeCost c)
        {
            var money = Fmt.Money(c.Money);
            if (S.money < c.Money) money = UIKit.Col(money, Pal.Red);
            var mat = Fmt.Num(c.Mat.Count) + " " + GameData.Resources[c.Mat.Res].Name;
            if (!E.HasMat(c.Mat)) mat = UIKit.Col(mat, Pal.Red);
            return money + " + " + mat;
        }
    }

    public class UIRoot
    {
        readonly GameController gc;
        public GameEngine Engine { get { return gc.Engine; } }
        public GameController Controller { get { return gc; } }

        Canvas canvas;
        RectTransform safe, overlay;
        Text money, income, worth, windowTitle;
        UIKit.Btn[] tabButtons;
        GameScreen[] screens;
        int current = -1;
        Rect lastSafe;

        Text toastText;
        Image toastBg;
        float toastUntil;

        RectTransform modal;

        static readonly string[] TabNames = { "Главная", "Бизнес", "Склад", "Биржа", "Компании", "Банк" };

        public UIRoot(GameController controller) { gc = controller; }

        public void Build()
        {
            if (UnityEngine.Object.FindObjectOfType<EventSystem>() == null)
            {
                var es = new GameObject("EventSystem", typeof(EventSystem), typeof(StandaloneInputModule));
                UnityEngine.Object.DontDestroyOnLoad(es);
            }

            var cgo = new GameObject("Canvas", typeof(RectTransform), typeof(Canvas), typeof(CanvasScaler), typeof(GraphicRaycaster));
            UnityEngine.Object.DontDestroyOnLoad(cgo);
            cgo.layer = 5;
            canvas = cgo.GetComponent<Canvas>();
            canvas.renderMode = RenderMode.ScreenSpaceOverlay;
            var scaler = cgo.GetComponent<CanvasScaler>();
            scaler.uiScaleMode = CanvasScaler.ScaleMode.ScaleWithScreenSize;
            scaler.referenceResolution = new Vector2(1080, 1920);
            scaler.screenMatchMode = CanvasScaler.ScreenMatchMode.MatchWidthOrHeight;
            scaler.matchWidthOrHeight = 0;

            var bg = UIKit.Img(cgo.transform, "Background", Pal.Bg, false);
            UIKit.Stretch(bg.rectTransform);

            safe = UIKit.Rect(cgo.transform, "SafeArea");
            UIKit.Stretch(safe);
            ApplySafeArea();

            BuildHeader();
            BuildWindow();
            BuildTabs();
            BuildToast();

            overlay = UIKit.Rect(safe, "Overlay");
            UIKit.Stretch(overlay);

            SwitchTab(0);
        }

        void ApplySafeArea()
        {
            var r = Screen.safeArea;
            if (r == lastSafe || Screen.width == 0 || Screen.height == 0) return;
            lastSafe = r;
            safe.anchorMin = new Vector2(r.xMin / Screen.width, r.yMin / Screen.height);
            safe.anchorMax = new Vector2(r.xMax / Screen.width, r.yMax / Screen.height);
            safe.offsetMin = safe.offsetMax = Vector2.zero;
        }

        // Верхняя панель: деньги, доход, капитал
        void BuildHeader()
        {
            var bar = UIKit.Img(safe, "TopBar", Pal.Panel);
            var rt = bar.rectTransform;
            rt.anchorMin = new Vector2(0, 1);
            rt.anchorMax = new Vector2(1, 1);
            rt.pivot = new Vector2(0.5f, 1);
            rt.offsetMin = new Vector2(20, -220);
            rt.offsetMax = new Vector2(-20, -16);

            var coin = UIKit.Rect(rt, "Coin").gameObject.AddComponent<Image>();
            coin.sprite = UIKit.Circle;
            coin.color = Pal.Gold;
            var crt = coin.rectTransform;
            crt.anchorMin = crt.anchorMax = new Vector2(0, 0.5f);
            crt.sizeDelta = new Vector2(120, 120);
            crt.anchoredPosition = new Vector2(96, 0);
            var ct = UIKit.Label(crt, "$", 72, Pal.GoldLip, TextAnchor.MiddleCenter, FontStyle.Bold);
            UIKit.Stretch(ct.rectTransform);

            money = UIKit.Label(rt, "$0", 76, Pal.Gold, TextAnchor.UpperLeft, FontStyle.Bold);
            var mrt = money.rectTransform;
            UIKit.Stretch(mrt, 184, 80, 24, 18);
            money.horizontalOverflow = HorizontalWrapMode.Overflow;
            var sh = money.gameObject.AddComponent<Shadow>();
            sh.effectColor = new Color(0, 0, 0, 0.35f);
            sh.effectDistance = new Vector2(0, -3);

            income = UIKit.Label(rt, "", 34, Pal.Green, TextAnchor.LowerLeft, FontStyle.Bold);
            UIKit.Stretch(income.rectTransform, 184, 26, 24, 110);

            worth = UIKit.Label(rt, "", 30, Pal.Muted, TextAnchor.LowerRight);
            UIKit.Stretch(worth.rectTransform, 184, 28, 32, 110);
        }

        // Центральное «окно» с шапкой, в котором живут экраны
        void BuildWindow()
        {
            var win = UIKit.Img(safe, "Window", Pal.Panel);
            var rt = win.rectTransform;
            UIKit.Stretch(rt, 20, 196, 20, 240);

            var head = UIKit.Img(rt, "Header", Pal.Header);
            var hrt = head.rectTransform;
            hrt.anchorMin = new Vector2(0, 1);
            hrt.anchorMax = new Vector2(1, 1);
            hrt.pivot = new Vector2(0.5f, 1);
            hrt.offsetMin = new Vector2(0, -100);
            hrt.offsetMax = Vector2.zero;
            windowTitle = UIKit.Label(hrt, "", 42, Pal.Title, TextAnchor.MiddleCenter, FontStyle.Bold);
            UIKit.Stretch(windowTitle.rectTransform);

            var area = UIKit.Rect(rt, "ScreenArea");
            UIKit.Stretch(area, 0, 8, 0, 100);

            screens = new GameScreen[]
            {
                new HomeScreen(this),
                new BusinessScreen(this),
                new WarehouseScreen(this),
                new MarketScreen(this),
                new CompanyScreen(this),
                new BankScreen(this),
            };
            foreach (var s in screens)
            {
                s.Create(area);
                UIKit.SetActive(s.Root, false);
            }
        }

        // Нижняя панель вкладок; активная — зелёная, как TAB 1 в референсе
        void BuildTabs()
        {
            var bar = UIKit.Img(safe, "Tabs", Pal.Header);
            var rt = bar.rectTransform;
            rt.anchorMin = Vector2.zero;
            rt.anchorMax = new Vector2(1, 0);
            rt.pivot = new Vector2(0.5f, 0);
            rt.offsetMin = new Vector2(20, 16);
            rt.offsetMax = new Vector2(-20, 180);
            var h = bar.gameObject.AddComponent<HorizontalLayoutGroup>();
            h.padding = new RectOffset(12, 12, 14, 14);
            h.spacing = 8;
            h.childControlWidth = h.childControlHeight = true;
            h.childForceExpandWidth = h.childForceExpandHeight = true;

            tabButtons = new UIKit.Btn[TabNames.Length];
            for (int i = 0; i < TabNames.Length; i++)
            {
                int idx = i;
                tabButtons[i] = UIKit.Button(rt, TabNames[i], Pal.Btn, () => SwitchTab(idx), 28, 0);
            }
        }

        void BuildToast()
        {
            toastBg = UIKit.Img(safe, "Toast", new Color(0.08f, 0.08f, 0.16f, 0.92f));
            var rt = toastBg.rectTransform;
            rt.anchorMin = new Vector2(0.5f, 0);
            rt.anchorMax = new Vector2(0.5f, 0);
            rt.pivot = new Vector2(0.5f, 0);
            rt.sizeDelta = new Vector2(940, 110);
            rt.anchoredPosition = new Vector2(0, 230);
            toastText = UIKit.Label(rt, "", 34, Pal.Text, TextAnchor.MiddleCenter, FontStyle.Bold);
            UIKit.Stretch(toastText.rectTransform, 20, 0, 20, 0);
            toastBg.raycastTarget = false;
            UIKit.SetActive(toastBg, false);
        }

        public void SwitchTab(int i)
        {
            if (i == current) { screens[i].ScrollTop(); return; }
            if (current >= 0) screens[current].Show(false);
            current = i;
            for (int t = 0; t < tabButtons.Length; t++) tabButtons[t].SetColor(t == i ? Pal.Green : Pal.Btn);
            windowTitle.text = screens[i].Title.ToUpperInvariant();
            screens[i].Show(true);
        }

        public GameScreen GetScreen(int i) { return screens[i]; }

        public void Refresh()
        {
            ApplySafeArea();
            var e = Engine;
            UIKit.SetText(money, Fmt.Money(e.S.money));
            UIKit.SetText(income, "+" + Fmt.Money(e.IncomePerSec) + " / сек");
            UIKit.SetText(worth, "Капитал: " + Fmt.Money(e.NetWorth()));

            if (e.StructureDirty)
            {
                e.StructureDirty = false;
                foreach (var s in screens) s.ForceRebuild();
            }
            if (current >= 0) screens[current].Refresh();

            if (toastBg.gameObject.activeSelf && Time.unscaledTime > toastUntil) UIKit.SetActive(toastBg, false);
        }

        public void Toast(string text)
        {
            toastText.text = text;
            toastUntil = Time.unscaledTime + 1.8f;
            UIKit.SetActive(toastBg, true);
            toastBg.transform.SetAsLastSibling();
        }

        // Выполняет действие; при неудаче показывает подсказку. После успеха сразу обновляет экран.
        public void Try(bool ok, string failText)
        {
            if (!ok) Toast(failText);
            Refresh();
        }

        public void OnBack()
        {
            if (modal != null) { CloseModal(); return; }
            if (current != 0) { SwitchTab(0); return; }
            gc.Save();
            GameController.MinimizeApp();
        }

        // ----- Модальные окна в стиле «INFO» -----
        public void ShowModal(string title, string text, string okText = "OK", Action onOk = null,
                              string cancelText = null, Color? okColor = null)
        {
            CloseModal();
            modal = UIKit.Rect(overlay, "Modal");
            UIKit.Stretch(modal);
            var dim = modal.gameObject.AddComponent<Image>();
            dim.color = new Color(0, 0, 0, 0.65f);

            var win = UIKit.Img(modal, "Window", Pal.Panel);
            var wrt = win.rectTransform;
            wrt.anchorMin = wrt.anchorMax = new Vector2(0.5f, 0.5f);
            wrt.sizeDelta = new Vector2(940, 0);
            var v = UIKit.VList(win, 20, 0);
            v.padding = new RectOffset(0, 0, 0, 32);
            var fit = win.gameObject.AddComponent<ContentSizeFitter>();
            fit.verticalFit = ContentSizeFitter.FitMode.PreferredSize;

            UIKit.Header(wrt, title);

            // внутренняя часть с боковыми отступами, шапка остаётся во всю ширину
            var inner = UIKit.Rect(wrt, "Inner");
            var iv = UIKit.VList(inner, 24, 0);
            iv.padding = new RectOffset(28, 28, 8, 0);

            var body = UIKit.Card(inner, Pal.Card, 28, 10);
            var txt = UIKit.Label(body, text, 34, Pal.Text, TextAnchor.UpperLeft);
            txt.lineSpacing = 1.1f;

            var row = UIKit.Row(inner, 120, 24);
            if (cancelText != null) UIKit.Button(row, cancelText, Pal.Blue, CloseModal, 36);
            UIKit.Button(row, okText, okColor ?? Pal.Green, () => { CloseModal(); if (onOk != null) onOk(); }, 36);
        }

        public void CloseModal()
        {
            if (modal == null) return;
            UnityEngine.Object.Destroy(modal.gameObject);
            modal = null;
        }

        public void ShowOfflineReport(OfflineReport rep)
        {
            var sb = new System.Text.StringBuilder();
            sb.Append("Вас не было: ").Append(Fmt.Time(rep.Seconds)).Append('\n');
            if (rep.Seconds >= GameData.OfflineCapSeconds) sb.Append("(учитывается максимум 8 часов)\n");
            sb.Append("\nЗаработано: ").Append(UIKit.Col(Fmt.Money(rep.Money), Pal.Gold)).Append('\n');
            for (int r = 0; r < rep.Res.Length; r++)
            {
                if (rep.Res[r] < 0.5) continue;
                sb.Append(GameData.Resources[r].Name).Append(": +").Append(Fmt.Num(rep.Res[r])).Append('\n');
            }
            ShowModal("С возвращением!", sb.ToString().TrimEnd(), "Отлично");
        }
    }
}
