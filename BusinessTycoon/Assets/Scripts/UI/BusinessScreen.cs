using System.Text;
using UnityEngine;
using UnityEngine.UI;

namespace Tycoon.UI
{
    // Бизнесы: покупка точек, улучшения за деньги + сырьё, цепочки производства
    public class BusinessScreen : GameScreen
    {
        public BusinessScreen(UIRoot ui) : base(ui) { }
        public override string Title { get { return "Бизнес"; } }

        static readonly int[] Modes = { 1, 10, 100, 0 };
        static readonly string[] ModeNames = { "×1", "×10", "×100", "MAX" };
        int mode = 1;

        class Item
        {
            public RectTransform Root, Details;
            public Text Name, Prod, Info, Locked;
            public UIKit.Btn Buy, Upgrade;
        }

        Item[] items;
        UIKit.Btn[] modeBtns;

        protected override void Build()
        {
            var hint = Body(Content, "Сырьё копится на складе: продавайте его или пускайте в переработку и на улучшения. " +
                                     "Каждые 10/25/50/100… точек удваивают производство.", 28);
            UIKit.LE(hint, -1);

            var mrow = UIKit.Row(Content, 96, 12);
            modeBtns = new UIKit.Btn[Modes.Length];
            for (int i = 0; i < Modes.Length; i++)
            {
                int m = Modes[i];
                modeBtns[i] = UIKit.Button(mrow, ModeNames[i], Pal.Btn, () => { mode = m; Sync(); }, 32, 0);
            }

            items = new Item[GameData.Businesses.Length];
            for (int c = 0; c < GameData.CategoryNames.Length; c++)
            {
                var h = UIKit.Label(Content, GameData.CategoryNames[c].ToUpperInvariant(), 34, Pal.Title, TextAnchor.MiddleCenter, FontStyle.Bold);
                UIKit.LE(h, 70);
                for (int i = 0; i < GameData.Businesses.Length; i++)
                    if ((int)GameData.Businesses[i].Cat == c) items[i] = BuildItem(i);
            }
        }

        Item BuildItem(int i)
        {
            var d = GameData.Businesses[i];
            var it = new Item();
            it.Root = Cell();

            var top = UIKit.Row(it.Root, 110, 20);
            UIKit.Badge(top, d.Abbr, d.Color, 100);
            var stack = VStack(top);
            it.Name = UIKit.Label(stack, d.Name, 36, Pal.Text, TextAnchor.MiddleLeft, FontStyle.Bold);
            it.Prod = UIKit.Label(stack, "", 28, Pal.Muted, TextAnchor.MiddleLeft);
            it.Locked = UIKit.Label(stack, "", 28, Pal.Muted, TextAnchor.MiddleLeft);

            it.Details = UIKit.Rect(it.Root, "Details");
            UIKit.VList(it.Details, 12, 0);
            it.Info = Body(it.Details, "", 28);
            var row = UIKit.Row(it.Details, 120, 16);
            int idx = i;
            it.Buy = UIKit.Button(row, "", Pal.Gold, () => UI.Try(E.BuyBiz(idx, mode), "Недостаточно средств"), 32, 0);
            it.Upgrade = UIKit.Button(row, "", Pal.Blue, () => UI.Try(E.UpgradeBiz(idx), "Не хватает денег или сырья"), 26, 0);
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
                UIKit.SetActive(it.Root, open || teaser);
                if (!open)
                {
                    if (teaser)
                    {
                        UIKit.SetActive(it.Details, false);
                        UIKit.SetActive(it.Prod, false);
                        UIKit.SetActive(it.Locked, true);
                        UIKit.SetText(it.Locked, "Откроется при " + Fmt.Money(d.Cost * 0.6));
                    }
                    continue;
                }
                UIKit.SetActive(it.Details, true);
                UIKit.SetActive(it.Prod, true);
                UIKit.SetActive(it.Locked, false);

                UIKit.SetText(it.Name, d.Name + (b.n > 0 ? "  ×" + b.n : "") + (b.lvl > 0 ? "  ур." + b.lvl : ""));
                UIKit.SetText(it.Prod, ProdLine(i, Mathf.Max(1, b.n)));
                UIKit.SetText(it.Info, InfoLine(i));

                int n = E.BuyAmount(i, mode);
                double cost = E.BizCost(i, n);
                it.Buy.Text = "Купить ×" + n + "\n" + Fmt.Money(cost);
                it.Buy.Interactable = S.money >= cost;

                if (b.lvl >= GameData.MaxBizLevel)
                {
                    it.Upgrade.Text = "Макс. уровень";
                    it.Upgrade.Interactable = false;
                }
                else
                {
                    var uc = E.BizUpgradeCost(i);
                    it.Upgrade.Text = "Улучшить ×2 (ур." + (b.lvl + 1) + ")\n" + CostText(uc);
                    it.Upgrade.Interactable = b.n > 0 && E.CanPay(uc);
                }
            }
        }

        // «+0.5 Зерно/сек» или «+$12/сек» — суммарно по всем точкам
        string ProdLine(int i, int units)
        {
            var d = GameData.Businesses[i];
            double k = units * E.BizMult(i);
            var sb = new StringBuilder();
            if (d.IsService)
            {
                sb.Append(UIKit.Col("+" + Fmt.Money(d.Cash * k) + "/сек", Pal.Green));
            }
            else
            {
                sb.Append(UIKit.Col("+" + Fmt.Num(d.OutQ * k) + " " + GameData.Resources[d.OutRes].Name + "/сек", Pal.Green));
                for (int j = 0; j < d.InRes.Length; j++)
                    sb.Append("  ").Append(UIKit.Col("-" + Fmt.Num(d.InQ[j] * k) + " " + GameData.Resources[d.InRes[j]].Name, Pal.Red));
            }
            if (S.biz[i].n == 0) sb.Append("  (за точку)");
            return sb.ToString();
        }

        string InfoLine(int i)
        {
            var b = S.biz[i];
            var sb = new StringBuilder();
            int next = GameEngine.NextMilestone(b.n);
            sb.Append("Множитель ×").Append(Fmt.Num(E.BizMult(i)));
            if (next > 0) sb.Append(" · до удвоения: ").Append(b.n).Append('/').Append(next);
            var issue = E.BizIssue[i];
            if (issue != null && b.n > 0) sb.Append('\n').Append(UIKit.Col("! " + issue, Pal.Gold));
            return sb.ToString();
        }
    }
}
