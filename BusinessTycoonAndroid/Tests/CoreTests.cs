using System;
using Tycoon;

// Консольные тесты игрового ядра (без Android):
//   mcs -out:core-tests.exe CoreTests.cs ../Core/*.cs && mono core-tests.exe
static class CoreTests
{
    static int fails;
    static void Check(bool ok, string what) { if (!ok) { fails++; Console.WriteLine("FAIL: " + what); } }

    static int Item(string id) { return GameData.ItemIndex(id); }
    static int Ind(string id) { for (int i = 0; i < GameData.Industries.Length; i++) if (GameData.Industries[i].Id == id) return i; return -1; }

    static void Main()
    {
        Formatting();
        Levels();
        BuyAndProduce();
        Stocks();
        ShopAndFounding();
        CompanyEconomics();
        ReorganizationAndIpo();
        Holding();
        MigrationFromV1();
        LevelGates();
        BotPlaythrough();
        Console.WriteLine(fails == 0 ? "ALL OK" : fails + " FAILED");
        Environment.Exit(fails == 0 ? 0 : 1);
    }

    static void Formatting()
    {
        Check(Fmt.Money(1234) == "$1.23K", "fmt K " + Fmt.Money(1234));
        Check(Fmt.Money(5.5e9) == "$5.50B", "fmt B");
        Check(Fmt.Time(125) == "2м 05с", "fmt time");
    }

    static void Levels()
    {
        var g = new GameEngine(null, 1);
        Check(g.S.level == 1, "start level 1");
        Check(!g.BuyBiz(1, 1), "sawmill locked at level 1");
        g.S.money = 1e6;
        Check(!g.BuyStock("YBLK", 1), "stocks locked below level 3");
        g.AddXp(GameData.XpForLevel(1) + GameData.XpForLevel(2) + 1);
        Check(g.S.level == 3, "level 3 after xp, got " + g.S.level);
        Check(g.BuyStock("YBLK", 1), "stocks unlocked at level 3");
        Check(g.Toasts.Count == 2, "level-up toasts");
        Check(g.BuyBiz(1, 1), "sawmill unlocked at level 2+");
    }

    static void BuyAndProduce()
    {
        var g = new GameEngine(null, 1);
        Check(g.BuyBiz(0, 1), "buy farm");
        for (int i = 0; i < 100; i++) g.Tick(0.1);
        Check(Math.Abs(g.S.res[0] - 5) < 0.01, "wheat produced " + g.S.res[0]);
        g.S.biz[0].n = 1000;
        for (int i = 0; i < 50; i++) g.Tick(0.1);
        Check(g.S.res[0] <= g.Capacity + 1e-6, "capacity respected");
        Check(g.BizIssue[0] == "Склад полон", "full issue");
    }

    static void Stocks()
    {
        Check(GameData.Stocks.Length >= 35, "many stocks: " + GameData.Stocks.Length);
        var g = new GameEngine(null, 2);
        g.S.level = 10;
        g.S.money = 1e5;
        Check(g.BuyStock("GZPR", 10), "buy stock");
        for (int i = 0; i < 300; i++) g.Tick(1);
        foreach (var st in g.S.stocks) Check(st.p > 0 && !double.IsNaN(st.p), "price sane " + st.t);
        Check(g.SellStock("GZPR", 10), "sell stock");
        Check(g.BuyOption("NVDM", true, 1.0, 60, 1), "buy option");
        for (int i = 0; i < 61; i++) g.Tick(1);
        Check(g.S.options.Count == 0, "option settled");
    }

    static void ShopAndFounding()
    {
        var g = new GameEngine(null, 3);
        int it = Ind("it");
        g.S.money = 1e5;
        Check(!g.BuyItem(Item("server")), "server locked at level 1");
        Check(g.FoundCompany("Старт", it, GameData.FormIP) != null, "cannot found at level 1");
        g.S.level = 3;
        Check(g.FoundCompany("Старт", it, GameData.FormIP) != null, "cannot found without laptop");
        Check(g.BuyItem(Item("laptop")), "buy laptop");
        Check(g.FoundCompany("", it, GameData.FormIP) != null, "name required");
        Check(g.FoundCompany("Старт", it, GameData.FormAO) != null, "cannot found AO directly");
        Check(g.FoundCompany("Старт", it, GameData.FormIP) == null, "found IP");
        var c = g.S.companies[0];
        Check(g.S.items[Item("laptop")] == 0 && c.seats == 1 && c.staff == 1, "laptop installed as seat");
        Check(g.Hire(c) != null, "no seat -> cannot hire");
        g.BuyItem(Item("laptop"), 3);
        Check(g.Hire(c, 3) == null && c.staff == 4 && c.seats == 4, "hire uses laptops");
        g.S.level = 6;
        Check(g.FoundCompany("Офис", it, GameData.FormOOO) != null, "OOO needs office");
    }

    static void CompanyEconomics()
    {
        var g = new GameEngine(null, 4);
        g.S.level = 5;
        g.S.money = 1e6;
        int it = Ind("it");
        g.BuyItem(Item("laptop"), 10);
        g.FoundCompany("Код", it, GameData.FormIP);
        var c = g.S.companies[0];
        g.Hire(c, 9);
        var s = g.Stats(c);
        Check(Math.Abs(s.Tax - s.Revenue * 0.06) < 1e-9, "IP pays 6% of revenue");
        Check(s.Net > 0, "10-person IT is profitable: " + s.Net);
        Check(s.Revenue <= s.Demand + 1e-9 && s.Revenue <= s.Capacity + 1e-9, "revenue limited");
        // перенаём без спроса — прибыль на сотрудника падает
        g.BuyItem(Item("laptop"), 5);
        g.Hire(c, 5);
        var s2 = g.Stats(c);
        Check(s2.Load < 1, "overstaffed -> load < 100%: " + s2.Load);
        Check(g.Marketing(c), "marketing");
        Check(g.Stats(c).Demand > s2.Demand, "marketing raises demand");
        Check(g.Hire(c) != null, "IP staff limit 15");

        // кассовый разрыв: убыточная компания без денег
        var g2 = new GameEngine(null, 5);
        g2.S.level = 5; g2.S.money = 1e5;
        g2.BuyItem(Item("laptop"), 15);
        g2.FoundCompany("Минус", it, GameData.FormIP);
        g2.Hire(g2.S.companies[0], 14);
        g2.S.companies[0].staff = 15;
        // сотрудники есть, спроса почти нет: искусственно
        g2.S.money = 0;
        var st = g2.Stats(g2.S.companies[0]);
        Console.WriteLine("  IT ИП 15 чел: выручка " + Fmt.Money(st.Revenue) + "/с, зарплаты " + Fmt.Money(st.Wages) + ", чистая " + Fmt.Money(st.Net));
    }

    static void ReorganizationAndIpo()
    {
        var g = new GameEngine(null, 6);
        g.S.level = 20;
        g.S.money = 1e10;
        int it = Ind("it");
        g.BuyItem(Item("laptop"), 400);
        g.FoundCompany("Рога и Копыта", it, GameData.FormIP);
        var c = g.S.companies[0];
        g.Hire(c, 14);
        Check(g.Reorganize(c) != null, "OOO needs office");
        g.BuyItem(Item("office_s"));
        Check(g.Reorganize(c) == null && c.form == GameData.FormOOO, "IP -> OOO");
        g.Hire(c, 60);
        for (int i = 0; i < 12; i++) g.Marketing(c);
        g.BuyItem(Item("office_b"));
        Check(g.Reorganize(c) == null && c.form == GameData.FormAO, "OOO -> AO: " + string.Join("; ", g.ReorgRequirements(c).ConvertAll(r => r.Text + (r.Ok ? "" : " ✗"))));
        Check(g.Reorganize(c) != null, "AO -> PAO only via IPO");
        g.Hire(c, 200);
        for (int i = 0; i < 12; i++) g.Marketing(c);
        g.Rnd(c);
        Console.WriteLine("  АО: оценка " + Fmt.Money(g.CoValuation(c)) + ", чистая " + Fmt.Money(g.Stats(c).Net) + "/с");
        foreach (var r in g.ReorgRequirements(c)) if (!r.Ok) Console.WriteLine("  не выполнено: " + r.Text);
        double m0 = g.S.money;
        Check(g.Ipo(c, 0.25) == null, "IPO");
        Check(c.pub && c.form == GameData.FormPAO && c.ticker == "ROGA", "PAO listed " + c.ticker);
        Check(g.S.money > m0 - GameData.Forms[GameData.FormPAO].Fee, "IPO raised cash");
        Check(Math.Abs(g.CoOwnFrac(c) - 0.75) < 1e-9, "own 75%");
    }

    static void Holding()
    {
        var g = new GameEngine(null, 7);
        g.S.level = 30;
        g.S.money = 1e10;
        g.BuyItem(Item("laptop"), 10);
        g.BuyItem(Item("office_s"), 3);
        for (int i = 0; i < 3; i++) Check(g.FoundCompany("Дочка" + i, Ind("it"), GameData.FormOOO) == null, "found OOO " + i);
        Check(g.CreateHolding("Империя") != null, "holding needs office A");
        g.BuyItem(Item("office_a"));
        double before = g.Stats(g.S.companies[0]).Revenue;
        Check(g.CreateHolding("Империя") == null && g.HasHolding, "holding created");
        Check(g.Stats(g.S.companies[0]).Revenue > before, "holding bonus");
        Check(Math.Abs(g.Stats(g.S.companies[0]).Tax - Math.Max(0, g.Stats(g.S.companies[0]).Profit) * 0.15) < 1e-9, "holding tax 15%");
    }

    static void LevelGates()
    {
        var g = new GameEngine(null, 9);
        g.S.level = GameData.GatedFromLevel;
        g.AddXp(g.XpToNext * 3);
        Check(g.S.level == GameData.GatedFromLevel && g.LevelBlocked, "level 15 blocked without course");
        Check(g.PromoteLevel() != null, "promote needs item");
        g.S.money = 1e9;
        Check(g.BuyItem(Item("course")), "buy course");
        Check(g.PromoteLevel() == null && g.S.level == 16, "promoted to 16");
        Check(GameData.LevelGate(16)[1] == 2, "level 16->17 needs 2 courses");
        Check(GameData.Items[GameData.LevelGate(20)[0]].Id == "iso", "level 20 needs ISO");

        // модернизация бизнеса выше 5-го уровня
        g.S.biz[0].n = 10;
        for (int r = 0; r < g.S.res.Length; r++) g.S.res[r] = 1e9;
        g.S.money = 1e12;
        for (int k = 0; k < 5; k++) Check(g.UpgradeBiz(0), "upgrade " + (k + 1));
        Check(!g.UpgradeBiz(0), "level 6 needs modkit");
        g.BuyItem(Item("modkit"));
        Check(g.UpgradeBiz(0) && g.S.biz[0].lvl == 6, "upgrade with modkit");
        Check(g.BizUpgradeCost(0).ItemCount == 2, "next needs 2 modkits");
    }

    static void MigrationFromV1()
    {
        var s = new GameState { v = 1, runEarned = 150000, level = 0 };
        s.companies.Add(new Company { id = 1, name = "Старая", ind = 3, staff = 7, scale = 80 });
        s.Normalize();
        Check(s.v == GameState.Version, "migrated version");
        Check(s.level >= 5 && s.level <= GameData.GatedFromLevel, "level from earnings: " + s.level);
        Check(s.companies[0].ind == 5 && s.companies[0].form == GameData.FormAO, "company migrated");
        Check(s.items.Length == GameData.Items.Length, "items array");
        Check(s.stocks.Count == GameData.Stocks.Length, "stocks added");
    }

    // Бот: покупает самое дешёвое доступное и продаёт сырьё. Смотрим темп уровней и денег.
    static void BotPlaythrough()
    {
        var g = new GameEngine(null, 8);
        for (int r = 0; r < g.S.auto.Length; r++) g.S.auto[r] = true;
        int[] marks = { 60, 600, 1800, 3600, 7200, 14400 };
        int mi = 0;
        for (int sec = 1; sec <= 14400; sec++)
        {
            if (sec < 300) for (int k = 0; k < 3; k++) g.Click();
            for (int step = 0; step < 10; step++) g.Tick(0.1);
            for (int tries = 0; tries < 20; tries++)
            {
                int best = -1; double bc = double.MaxValue;
                for (int i = 0; i < GameData.Businesses.Length; i++)
                {
                    if (!g.BizUnlocked(i)) continue;
                    double c = g.BizCost(i, 1);
                    if (c < bc && c <= g.S.money) { bc = c; best = i; }
                }
                if (g.ClickUpgradeCost < bc && g.ClickUpgradeCost <= g.S.money) { g.UpgradeClick(); continue; }
                if (best < 0) break;
                g.BuyBiz(best, 1);
            }
            if (mi < marks.Length && sec == marks[mi])
            {
                Console.WriteLine(string.Format("  t={0,6}s ур.{1,2} деньги={2,9} доход={3,9}/с капитал={4,9}",
                    sec, g.S.level, Fmt.Money(g.S.money), Fmt.Money(g.IncomePerSec), Fmt.Money(g.NetWorth())));
                mi++;
            }
        }
        Check(g.S.level >= 10 && g.S.level <= GameData.GatedFromLevel, "bot level in 4h: " + g.S.level);
        Check(!double.IsNaN(g.S.money), "no NaN");
    }
}
