using Android.Widget;

namespace Tycoon.Droid
{
    // Магазин: оборудование, транспорт, недвижимость, лицензии и предметы для повышения уровня
    public class ShopScreen : GameScreen
    {
        public ShopScreen(Shell ui) : base(ui) { }
        public override string Title { get { return "Магазин"; } }

        int cat = -1;   // -1 — все категории
        Btn[] catBtns;

        class Item
        {
            public LinearLayout Root;
            public TextView Name, Info;
            public Btn Buy1, Buy5, Sell;
        }

        Item[] items;

        protected override void Build()
        {
            Ui.Body(Content, 13).Text = "Оборудование нужно для открытия компаний, рабочих мест сотрудников и ускорения бизнеса. " +
                                        "Товары открываются с ростом уровня. Ненужное можно продать за 50% цены.";

            int n = GameData.ItemCatNames.Length + 1;
            catBtns = new Btn[n];
            LinearLayout row = null;
            for (int i = 0; i < n; i++)
            {
                if (i % 4 == 0) row = Ui.Row(Content, 42, 6);
                int c = i - 1;
                string name = c < 0 ? "Все" : GameData.ItemCatNames[c];
                catBtns[i] = Ui.Button(row, name, Pal.Btn, () => { cat = c; Sync(); ScrollTop(); }, 12, 0);
            }

            items = new Item[GameData.Items.Length];
            for (int i = 0; i < items.Length; i++) items[i] = BuildItem(i);
        }

        Item BuildItem(int i)
        {
            var d = GameData.Items[i];
            var it = new Item { Root = Cell() };
            var top = Ui.Row(it.Root, 0, 12);
            Ui.Badge(top, IconOf(d.Id), 48);
            var st = Ui.Stack(top);
            it.Name = Ui.Label(st, d.Name, 16, Pal.Text, true);
            it.Info = Ui.Label(st, "", 13, Pal.Muted);
            var row = Ui.Row(it.Root, 52, 8);
            int idx = i;
            it.Buy1 = Ui.Button(row, "", Pal.Gold, () => UI.Try(E.BuyItem(idx, 1), "Недостаточно средств"), 14, 0);
            it.Buy5 = Ui.Button(row, "", Pal.Gold, () => UI.Try(E.BuyItem(idx, 5), "Недостаточно средств"), 14, 0);
            it.Sell = Ui.Button(row, "Продать 1", Pal.Btn, () => UI.Try(E.SellItem(idx), "Нечего продавать"), 14, 0);
            return it;
        }

        protected override void Sync()
        {
            for (int i = 0; i < catBtns.Length; i++) catBtns[i].SetColor(i - 1 == cat ? Pal.Green : Pal.Btn);
            for (int i = 0; i < items.Length; i++)
            {
                var d = GameData.Items[i];
                var it = items[i];
                bool show = cat < 0 || (int)d.Cat == cat;
                Ui.Show(it.Root, show);
                if (!show) continue;
                bool open = E.ItemUnlocked(i);
                var r = new Rich();
                if (!open) r.C("🔒 Нужен уровень " + d.ReqLevel, Pal.Gold).N();
                r.T(d.Desc).N().T("Цена ").B(Fmt.Money(d.Price), Pal.Gold).T(" · на складе: ").B(S.items[i].ToString(), Pal.Text);
                Ui.Set(it.Info, r);
                it.Buy1.Text = "Купить\n" + Fmt.Money(d.Price);
                it.Buy5.Text = "Купить 5\n" + Fmt.Money(d.Price * 5);
                it.Buy1.Enabled = open && S.money >= d.Price;
                it.Buy5.Enabled = open && S.money >= d.Price * 5;
                it.Sell.Enabled = S.items[i] > 0;
            }
        }
    }
}
