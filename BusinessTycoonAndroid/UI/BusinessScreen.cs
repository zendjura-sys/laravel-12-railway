using Android.Views;
using Android.Widget;

namespace Tycoon.Droid
{
    // Бизнесы: покупка точек, улучшения за деньги + сырьё, цепочки производства
    public class BusinessScreen : GameScreen
    {
        public BusinessScreen(Shell ui) : base(ui) { }
        public override string Title { get { return "Бизнес"; } }

        static readonly int[] Modes = { 1, 10, 100, 0 };
        static readonly string[] ModeNames = { "×1", "×10", "×100", "MAX" };
        int mode = 1;

        class Item
        {
            public LinearLayout Root, Details;
            public TextView Name, Prod, Info, Locked;
            public Btn Buy, Upgrade;
        }

        Item[] items;
        Btn[] modeBtns;

        protected override void Build()
        {
            var hint = Ui.Body(Content, 13);
            hint.Text = "Сырьё копится на складе: продавайте его или пускайте в переработку и на улучшения. " +
                        "Каждые 10/25/50/100… точек удваивают производство.";

            var mrow = Ui.Row(Content, 46, 8);
            modeBtns = new Btn[Modes.Length];
            for (int i = 0; i < Modes.Length; i++)
            {
                int m = Modes[i];
                modeBtns[i] = Ui.Button(mrow, ModeNames[i], Pal.Btn, () => { mode = m; Sync(); }, 15, 0);
            }

            items = new Item[GameData.Businesses.Length];
            for (int c = 0; c < GameData.CategoryNames.Length; c++)
            {
                var h = Ui.Label(Content, GameData.CategoryNames[c].ToUpperInvariant(), 15, Pal.Title, true, GravityFlags.Center);
                h.SetPadding(0, Ui.Dp(6), 0, 0);
                for (int i = 0; i < GameData.Businesses.Length; i++)
                    if ((int)GameData.Businesses[i].Cat == c) items[i] = BuildItem(i);
            }
        }

        Item BuildItem(int i)
        {
            var d = GameData.Businesses[i];
            var it = new Item();
            it.Root = Cell();

            var top = Ui.Row(it.Root, 0, 12);
            Ui.Badge(top, Icons.Of(d.Id), 50);
            var stack = Ui.Stack(top);
            it.Name = Ui.Label(stack, d.Name, 17, Pal.Text, true);
            it.Prod = Ui.Label(stack, "", 13, Pal.Muted);
            it.Locked = Ui.Label(stack, "", 13, Pal.Muted);

            it.Details = Ui.VList(it.Root, 8);
            it.Info = Ui.Body(it.Details, 13);
            var row = Ui.Row(it.Details, 56, 8);
            int idx = i;
            it.Buy = Ui.Button(row, "", Pal.Gold, () => UI.Try(E.BuyBiz(idx, mode), "Недостаточно средств"), 15, 0);
            it.Upgrade = Ui.Button(row, "", Pal.Blue, () => UI.Try(E.UpgradeBiz(idx), "Не хватает денег или сырья"), 13, 0);
            return it;
        }

        protected override void Sync()
        {
            for (int m = 0; m < Modes.Length; m++) modeBtns[m].SetColor(Modes[m] == mode ? Pal.Green : Pal.Btn);

            // Показываем открытые бизнесы и по одному «замку» в каждой категории
            var teaserShown = new bool[GameData.CategoryNames.Length];
            for (int i = 0; i < items.Length; i++)
            {
                var d = GameData.Businesses[i];
                var b = S.biz[i];
                var it = items[i];
                bool open = b.seen || b.n > 0;
                int cat = (int)d.Cat;
                bool teaser = !open && !teaserShown[cat];
                if (teaser) teaserShown[cat] = true;
                Ui.Show(it.Root, open || teaser);
                if (!open)
                {
                    if (teaser)
                    {
                        Ui.Show(it.Details, false);
                        Ui.Show(it.Prod, false);
                        Ui.Show(it.Locked, true);
                        Ui.Set(it.Locked, "🔒 Откроется при " + Fmt.Money(d.Cost * 0.6));
                    }
                    continue;
                }
                Ui.Show(it.Details, true);
                Ui.Show(it.Prod, true);
                Ui.Show(it.Locked, false);

                Ui.Set(it.Name, d.Name + (b.n > 0 ? "  ×" + b.n : "") + (b.lvl > 0 ? "  ур." + b.lvl : ""));
                Ui.Set(it.Prod, ProdLine(i, System.Math.Max(1, b.n)));
                Ui.Set(it.Info, InfoLine(i));

                int n = E.BuyAmount(i, mode);
                double cost = E.BizCost(i, n);
                it.Buy.Text = "Купить ×" + n + "\n" + Fmt.Money(cost);
                it.Buy.Enabled = S.money >= cost;

                if (b.lvl >= GameData.MaxBizLevel)
                {
                    it.Upgrade.Text = "Макс. уровень";
                    it.Upgrade.Enabled = false;
                }
                else
                {
                    var uc = E.BizUpgradeCost(i);
                    it.Upgrade.SetRich(Cost(new Rich().T("Улучшить ×2 (ур." + (b.lvl + 1) + ")\n"), uc));
                    it.Upgrade.Enabled = b.n > 0 && E.CanPay(uc);
                }
            }
        }

        // «+0.5 Зерно/сек» или «+$12/сек» — суммарно по всем точкам
        Rich ProdLine(int i, int units)
        {
            var d = GameData.Businesses[i];
            double k = units * E.BizMult(i);
            var r = new Rich();
            if (d.IsService)
            {
                r.C("+" + Fmt.Money(d.Cash * k) + "/сек", Pal.Green);
            }
            else
            {
                var o = GameData.Resources[d.OutRes];
                r.C("+" + Fmt.Num(d.OutQ * k) + " " + Icons.Of(o.Id) + "/сек", Pal.Green);
                for (int j = 0; j < d.InRes.Length; j++)
                    r.T("  ").C("-" + Fmt.Num(d.InQ[j] * k) + " " + Icons.Of(GameData.Resources[d.InRes[j]].Id), Pal.Red);
            }
            if (S.biz[i].n == 0) r.T("  (за точку)");
            return r;
        }

        Rich InfoLine(int i)
        {
            var b = S.biz[i];
            var r = new Rich();
            int next = GameEngine.NextMilestone(b.n);
            r.T("Множитель ×" + Fmt.Num(E.BizMult(i)));
            if (next > 0) r.T(" · до удвоения: " + b.n + "/" + next);
            var issue = E.BizIssue[i];
            if (issue != null && b.n > 0) r.N().C("⚠ " + issue, Pal.Gold);
            return r;
        }
    }
}
