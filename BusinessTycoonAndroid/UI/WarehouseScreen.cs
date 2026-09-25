using Android.Views;
using Android.Widget;

namespace Tycoon.Droid
{
    // Склад и рынок сырья: продажа, автопродажа, расширение склада
    public class WarehouseScreen : GameScreen
    {
        public WarehouseScreen(Shell ui) : base(ui) { }
        public override string Title { get { return "Склад"; } }

        class Item
        {
            public LinearLayout Root;
            public TextView Amount, Price;
            public ProgressBar Fill;
            public Btn Half, All, Auto;
        }

        Item[] items;
        TextView storeInfo, empty;
        Btn storeUp;

        protected override void Build()
        {
            var sc = Cell();
            Ui.Subtitle(sc, "Склад");
            storeInfo = Ui.Body(sc, 14);
            storeUp = Ui.Button(sc, "", Pal.Blue, () => UI.Try(E.UpgradeStore(), "Не хватает денег или сырья"), 14, 54);
            var hint = Ui.Body(sc, 12);
            hint.Text = "Цены на сырьё меняются каждую секунду и от новостей. Автопродажа сбывает остатки " +
                        "после переработки, торговый агент берёт 10%.";

            empty = Ui.Body(Content, 15);
            empty.Text = "Склад пуст. Купите ферму или лесопилку во вкладке «Бизнес».";

            items = new Item[GameData.Resources.Length];
            for (int r = 0; r < items.Length; r++) items[r] = BuildItem(r);
        }

        Item BuildItem(int r)
        {
            var d = GameData.Resources[r];
            var it = new Item();
            it.Root = Cell();

            var top = Ui.Row(it.Root, 0, 12);
            Ui.Badge(top, Icons.Of(d.Id), 46);
            var stack = Ui.Stack(top);
            Ui.Label(stack, d.Name, 16, Pal.Text, true);
            it.Amount = Ui.Label(stack, "", 13, Pal.Muted);
            it.Price = Ui.Label(top, "", 15, Pal.Gold, true, GravityFlags.End | GravityFlags.CenterVertical);

            it.Fill = Ui.Progress(it.Root, Pal.Gold);

            var row = Ui.Row(it.Root, 50, 8);
            int idx = r;
            it.Half = Ui.Button(row, "Продать 50%", Pal.Gold, () => Sell(idx, 0.5), 13, 0);
            it.All = Ui.Button(row, "Продать всё", Pal.Gold, () => Sell(idx, 1), 13, 0);
            it.Auto = Ui.Button(row, "", Pal.Btn, () => { S.auto[idx] = !S.auto[idx]; Sync(); }, 13, 0);
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
            Ui.Set(storeInfo, "Уровень " + S.storeLvl + " · вместимость " + Fmt.Num(cap) + " ед. каждого ресурса");
            var uc = E.StoreUpgradeCost();
            storeUp.SetRich(Cost(new Rich().T("Расширить склад\n"), uc));
            storeUp.Enabled = E.CanPay(uc);

            bool any = false;
            for (int r = 0; r < items.Length; r++)
            {
                var it = items[r];
                bool vis = E.ResVisible(r);
                any |= vis;
                Ui.Show(it.Root, vis);
                if (!vis) continue;

                double amt = S.res[r], rate = E.ResRates[r];
                var a = new Rich().T(Fmt.Num(amt) + " / " + Fmt.Num(cap));
                if (System.Math.Abs(rate) >= 1e-4)
                    a.T("  ").C((rate > 0 ? "+" : "-") + Fmt.Num(System.Math.Abs(rate)) + "/сек", Ui.Sign(rate));
                Ui.Set(it.Amount, a);

                double m = S.mkt[r];
                Ui.Set(it.Price, new Rich().T(Fmt.Money(E.ResPrice(r))).N().Small((m >= 1 ? "▲ " : "▼ ") + Fmt.Pct(m - 1), Ui.Sign(m - 1)));

                it.Fill.Progress = (int)(System.Math.Min(1, amt / cap) * 1000);
                it.Half.Enabled = amt >= 0.01;
                it.All.Enabled = amt >= 0.01;
                it.Auto.Text = S.auto[r] ? "Авто: ВКЛ" : "Авто: ВЫКЛ";
                it.Auto.SetColor(S.auto[r] ? Pal.Green : Pal.Btn);
            }
            Ui.Show(empty, !any);
        }
    }
}
