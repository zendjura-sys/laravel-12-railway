using UnityEngine;
using UnityEngine.UI;

namespace Tycoon.UI
{
    // Склад и рынок сырья: продажа, автопродажа, расширение склада
    public class WarehouseScreen : GameScreen
    {
        public WarehouseScreen(UIRoot ui) : base(ui) { }
        public override string Title { get { return "Склад"; } }

        class Item
        {
            public RectTransform Root;
            public Text Amount, Price;
            public Image Fill;
            public UIKit.Btn Half, All, Auto;
        }

        Item[] items;
        Text storeInfo, empty;
        UIKit.Btn storeUp;

        protected override void Build()
        {
            var sc = Cell();
            Subtitle(sc, "Склад");
            storeInfo = Body(sc, "", 30);
            storeUp = UIKit.Button(sc, "", Pal.Blue, () => UI.Try(E.UpgradeStore(), "Не хватает денег или сырья"), 30);
            var hint = Body(sc, "Цены на сырьё меняются каждую секунду и от новостей. Автопродажа сбывает остатки " +
                                "после переработки, торговый агент берёт 10%.", 26);
            UIKit.LE(hint, -1);

            empty = Body(Content, "Склад пуст. Купите ферму или лесопилку во вкладке «Бизнес».", 32);

            items = new Item[GameData.Resources.Length];
            for (int r = 0; r < items.Length; r++) items[r] = BuildItem(r);
        }

        Item BuildItem(int r)
        {
            var d = GameData.Resources[r];
            var it = new Item();
            it.Root = Cell();

            var top = UIKit.Row(it.Root, 100, 20);
            UIKit.Badge(top, d.Abbr, d.Color, 92);
            var stack = VStack(top);
            UIKit.Label(stack, d.Name, 34, Pal.Text, TextAnchor.MiddleLeft, FontStyle.Bold);
            it.Amount = UIKit.Label(stack, "", 28, Pal.Muted, TextAnchor.MiddleLeft);
            it.Price = UIKit.Label(top, "", 32, Pal.Gold, TextAnchor.MiddleRight, FontStyle.Bold);
            UIKit.LE(it.Price, -1, 0, 260);

            // полоска заполненности
            var bar = UIKit.Img(it.Root, "Bar", Pal.CardAlt);
            UIKit.LE(bar, 18);
            it.Fill = UIKit.Img(bar.transform, "Fill", Pal.Gold);
            var frt = it.Fill.rectTransform;
            frt.anchorMin = Vector2.zero;
            frt.anchorMax = new Vector2(0, 1);
            frt.offsetMin = frt.offsetMax = Vector2.zero;

            var row = UIKit.Row(it.Root, 110, 14);
            int idx = r;
            it.Half = UIKit.Button(row, "Продать 50%", Pal.Gold, () => Sell(idx, 0.5), 28, 0);
            it.All = UIKit.Button(row, "Продать всё", Pal.Gold, () => Sell(idx, 1), 28, 0);
            it.Auto = UIKit.Button(row, "", Pal.Btn, () => { S.auto[idx] = !S.auto[idx]; Sync(); }, 28, 0);
            return it;
        }

        void Sell(int r, double frac)
        {
            double v = E.SellRes(r, frac);
            if (v > 0) UI.Toast("Продано на " + Fmt.Money(v));
            UI.Refresh();
        }

        protected override void Sync()
        {
            double cap = E.Capacity;
            UIKit.SetText(storeInfo, "Уровень " + S.storeLvl + " · вместимость " + Fmt.Num(cap) + " ед. каждого ресурса");
            var uc = E.StoreUpgradeCost();
            storeUp.Text = "Расширить склад: " + CostText(uc);
            storeUp.Interactable = E.CanPay(uc);

            bool any = false;
            for (int r = 0; r < items.Length; r++)
            {
                var it = items[r];
                bool vis = E.ResVisible(r);
                any |= vis;
                UIKit.SetActive(it.Root, vis);
                if (!vis) continue;

                double amt = S.res[r], rate = E.ResRates[r];
                var rateS = Mathf.Abs((float)rate) < 1e-4 ? "" :
                    "  " + UIKit.Col((rate > 0 ? "+" : "-") + Fmt.Num(System.Math.Abs(rate)) + "/сек", rate > 0 ? Pal.Green : Pal.Red);
                UIKit.SetText(it.Amount, Fmt.Num(amt) + " / " + Fmt.Num(cap) + rateS);

                double m = S.mkt[r];
                var trend = UIKit.Col(Fmt.Pct(m - 1), m >= 1 ? Pal.Green : Pal.Red);
                UIKit.SetText(it.Price, Fmt.Money(E.ResPrice(r)) + "\n<size=24>" + trend + "</size>");

                it.Fill.rectTransform.anchorMax = new Vector2(Mathf.Clamp01((float)(amt / cap)), 1);
                it.Half.Interactable = amt >= 0.01;
                it.All.Interactable = amt >= 0.01;
                it.Auto.Text = S.auto[r] ? "Авто: ВКЛ" : "Авто: ВЫКЛ";
                it.Auto.SetColor(S.auto[r] ? Pal.Green : Pal.Btn);
            }
            UIKit.SetActive(empty, !any);
        }
    }
}
