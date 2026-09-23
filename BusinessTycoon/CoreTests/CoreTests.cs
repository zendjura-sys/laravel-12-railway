using System;
using Tycoon;

// Консольные тесты игрового ядра (без Unity): mcs CoreTests.cs ../Assets/Scripts/Core/*.cs
static class CoreTests
{
    static int fails;
    static void Check(bool ok, string what) { if (!ok) { fails++; Console.WriteLine("FAIL: " + what); } }

    static void Main()
    {
        Formatting();
        BuyAndProduce();
        StocksAndOptions();
        CompanyAndIpo();
        Bank();
        BotPlaythrough();
        Console.WriteLine(fails == 0 ? "ALL OK" : fails + " FAILED");
        Environment.Exit(fails == 0 ? 0 : 1);
    }

    static void Formatting()
    {
        Check(Fmt.Money(1234) == "$1.23K", "fmt K " + Fmt.Money(1234));
        Check(Fmt.Money(5.5e9) == "$5.50B", "fmt B " + Fmt.Money(5.5e9));
        Check(Fmt.Num(12.5) == "12.5", "fmt small " + Fmt.Num(12.5));
        Check(Fmt.Time(125) == "2м 05с", "fmt time " + Fmt.Time(125));
    }

    static void BuyAndProduce()
    {
        var g = new GameEngine(null, 1);
        int farm = 0;
        Check(g.BuyBiz(farm, 1), "buy farm");
        Check(Math.Abs(g.S.money - 5) < 1e-9, "farm cost");
        for (int i = 0; i < 100; i++) g.Tick(0.1);
        Check(Math.Abs(g.S.res[0] - 5) < 0.01, "wheat produced " + g.S.res[0]);
        g.SellRes(0, 1);
        Check(g.S.res[0] < 1e-9 && g.S.money > 5, "sell wheat");
        // Склад ограничивает производство
        g.S.biz[farm].n = 1000;
        for (int i = 0; i < 50; i++) g.Tick(0.1);
        Check(g.S.res[0] <= g.Capacity + 1e-6, "capacity respected");
        Check(g.BizIssue[farm] == "Склад полон", "full issue");
        // Переработка потребляет сырьё
        g.S.biz[4].n = 1; // пекарня
        g.Tick(1);
        Check(g.S.res[2] > 0, "bread produced");
        // Улучшение требует материалов
        g.S.money = 1e6;
        var c = g.BizUpgradeCost(farm);
        Check(!g.UpgradeBiz(farm) || g.HasMat(c.Mat), "upgrade needs mat");
        g.S.res[c.Mat.Res] = c.Mat.Count;
        Check(g.UpgradeBiz(farm) && g.S.biz[farm].lvl == 1, "upgrade ok");
        // Максимальная покупка не уходит в минус
        g.S.money = 12345;
        int n = g.BizMaxAffordable(1);
        Check(g.BizCost(1, n) <= 12345 && g.BizCost(1, n + 1) > 12345, "max affordable");
    }

    static void StocksAndOptions()
    {
        var g = new GameEngine(null, 2);
        g.S.money = 1e5;
        Check(g.BuyStock("NEFT", 10), "buy stock");
        Check(g.Stock("NEFT").own == 10, "own 10");
        for (int i = 0; i < 600; i++) g.Tick(1);
        foreach (var st in g.S.stocks) Check(st.p > 0 && !double.IsNaN(st.p), "price sane " + st.t + " " + st.p);
        Check(g.SellStock("NEFT", 100), "sell stock");
        Check(g.Stock("NEFT").own == 0, "sold all");

        var q = g.Quote("KREM", true, 1.0, 60);
        Check(q.Premium > 0, "call premium > 0");
        double put = g.Quote("KREM", false, 1.0, 60).Premium;
        Check(Math.Abs(put - q.Premium) / q.Premium < 0.05, "ATM put≈call " + put + " " + q.Premium);
        Check(g.BuyOption("KREM", true, 1.0, 60, 2), "buy option");
        Check(g.S.options.Count == 1, "option held");
        for (int i = 0; i < 61; i++) g.Tick(1);
        Check(g.S.options.Count == 0, "option settled");
        Check(Math.Abs(OptionFairCheck()) < 1e-9, "intrinsic at expiry");
    }

    static double OptionFairCheck()
    {
        return GameEngine.OptionFair(true, 110, 100, 0, 0.01) - 10 + GameEngine.OptionFair(false, 110, 100, 0, 0.01);
    }

    static void CompanyAndIpo()
    {
        var g = new GameEngine(null, 3);
        Check(g.FoundCompany("Рога и Копыта", 0) == "Недостаточно средств", "found needs money");
        g.S.money = 1e7;
        Check(g.FoundCompany("", 0) != null, "name required");
        Check(g.FoundCompany("Рога и Копыта", 0) == null, "found ok");
        var c = g.S.companies[0];
        for (int i = 0; i < 40; i++) g.CoHire(c);
        for (int i = 0; i < 5; i++) g.CoMarketing(c);
        Check(g.CoValuation(c) >= g.IpoMin(c), "ipo eligible " + g.CoValuation(c));
        double m0 = g.S.money;
        Check(g.Ipo(c, 0.25), "ipo");
        Check(c.ticker == "ROGA", "ticker " + c.ticker);
        Check(g.S.money > m0, "ipo cash");
        Check(Math.Abs(g.CoOwnFrac(c) - 0.75) < 1e-9, "own 75%");
        for (int i = 0; i < 100; i++) g.Tick(1);
        Check(g.Stock(c.ticker).p > 0, "own stock trades");
        Check(g.MaxBuyShares(c.ticker) <= 2500, "cannot buy more than float");
    }

    static void Bank()
    {
        var g = new GameEngine(null, 4);
        g.S.money = 1000;
        Check(g.Deposit(1000) && g.S.money == 0, "deposit");
        g.Tick(60);
        Check(g.S.bankDep > 1000, "interest");
        Check(g.TakeLoan(1e12) && g.S.bankLoan <= g.LoanLimit + 1e-6, "loan limited");
        Check(g.Repay(1e12), "repay");
    }

    // Простой бот: всё время покупает самое выгодное и продаёт сырьё. Проверяем, что прогресс идёт.
    static void BotPlaythrough()
    {
        var g = new GameEngine(null, 5);
        for (int r = 0; r < g.S.auto.Length; r++) g.S.auto[r] = true;
        double[] marks = { 60, 600, 1800, 3600, 7200, 14400 };
        int mi = 0;
        for (int sec = 1; sec <= 14400; sec++)
        {
            if (sec < 300) for (int k = 0; k < 3; k++) g.Click();
            for (int step = 0; step < 10; step++) g.Tick(0.1);
            // покупаем самое дешёвое из доступного, пока хватает денег
            for (int tries = 0; tries < 20; tries++)
            {
                int best = -1; double bc = double.MaxValue;
                for (int i = 0; i < GameData.Businesses.Length; i++)
                {
                    double c = g.BizCost(i, 1);
                    if (c < bc && c <= g.S.money) { bc = c; best = i; }
                }
                if (g.ClickUpgradeCost < bc && g.ClickUpgradeCost <= g.S.money) { g.UpgradeClick(); continue; }
                if (best < 0) break;
                g.BuyBiz(best, 1);
            }
            if (mi < marks.Length && sec == marks[mi])
            {
                int owned = 0; foreach (var b in g.S.biz) owned += b.n;
                Console.WriteLine(string.Format("t={0,6}s money={1,9} income={2,9}/s nw={3,9} units={4}",
                    sec, Fmt.Money(g.S.money), Fmt.Money(g.IncomePerSec), Fmt.Money(g.NetWorth()), owned));
                mi++;
            }
        }
        Check(g.NetWorth() > 1e6, "bot reaches $1M in 4h");
        Check(!double.IsNaN(g.S.money), "no NaN");
        var rep = g.SimulateOffline(3600);
        Console.WriteLine("offline 1h: +" + Fmt.Money(rep.Money));
        Check(rep.Money > 0, "offline earns");
    }
}
