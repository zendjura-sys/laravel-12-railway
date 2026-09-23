using System;
using System.Collections.Generic;

// Сохраняемое состояние игры. Только публичные поля, массивы и List —
// чтобы сериализовалось стандартным JsonUtility.
namespace Tycoon
{
    [Serializable]
    public class BizState
    {
        public int n;
        public int lvl;
        public bool seen;
    }

    [Serializable]
    public class StockState
    {
        public string t;
        public double p;     // текущая цена
        public double f;     // справедливая цена
        public double p0;    // цена на старте (для % изменения)
        public long own;     // акций у игрока
        public double avg;   // средняя цена покупки
        public List<double> h = new List<double>();
    }

    [Serializable]
    public class OptionPos
    {
        public int id;
        public string t;
        public bool call;
        public double strike;
        public int qty;
        public double expiry;  // игровое время экспирации
        public double paid;
    }

    [Serializable]
    public class Company
    {
        public int id;
        public string name;
        public int ind;
        public double scale;
        public int staff;
        public int mkt;
        public int rnd;
        public bool pub;
        public string ticker;
    }

    [Serializable]
    public class NewsItem
    {
        public string text;
        public int kind;   // 0 — нейтрально, 1 — хорошо, -1 — плохо
    }

    [Serializable]
    public class GameState
    {
        public int v = 1;
        public double money = GameData.StartMoney;
        public double t;
        public double runEarned;
        public double totalEarned;
        public int rep;
        public int prestiges;
        public int clickLvl;
        public long clicks;
        public int storeLvl;

        public double[] res;
        public bool[] auto;
        public double[] mkt;
        public BizState[] biz;

        public List<StockState> stocks = new List<StockState>();
        public List<OptionPos> options = new List<OptionPos>();
        public List<Company> companies = new List<Company>();
        public List<NewsItem> news = new List<NewsItem>();

        public double bankDep;
        public double bankLoan;
        public double nextEvent = 40;
        public int optId = 1;
        public int coId = 1;
        public long lastSeenUnix;

        public static GameState Create()
        {
            var s = new GameState();
            s.Normalize();
            return s;
        }

        // Дополняет недостающие поля (новая игра или старое сохранение после обновления)
        public void Normalize()
        {
            int nr = GameData.Resources.Length;
            res = Resize(res, nr, 0.0);
            mkt = Resize(mkt, nr, 1.0);
            auto = Resize(auto, nr, false);
            var oldBiz = biz ?? new BizState[0];
            biz = new BizState[GameData.Businesses.Length];
            for (int i = 0; i < biz.Length; i++) biz[i] = i < oldBiz.Length && oldBiz[i] != null ? oldBiz[i] : new BizState();
            if (stocks == null) stocks = new List<StockState>();
            if (options == null) options = new List<OptionPos>();
            if (companies == null) companies = new List<Company>();
            if (news == null) news = new List<NewsItem>();
            foreach (var d in GameData.Stocks)
            {
                if (stocks.Exists(x => x.t == d.Ticker)) continue;
                var st = new StockState { t = d.Ticker, p = d.Price, f = d.Price, p0 = d.Price };
                st.h.Add(d.Price);
                stocks.Add(st);
            }
            foreach (var st in stocks) if (st.h == null) st.h = new List<double> { st.p };
        }

        static T[] Resize<T>(T[] a, int n, T def)
        {
            var r = new T[n];
            for (int i = 0; i < n; i++) r[i] = a != null && i < a.Length ? a[i] : def;
            return r;
        }
    }
}
