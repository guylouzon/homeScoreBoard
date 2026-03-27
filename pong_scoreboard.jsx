import React, { useState, useMemo } from 'react';
import { Trophy, Users, Zap, Moon, Sun, Globe } from 'lucide-react';

export default function PongScoreboard() {
  const [isDark, setIsDark] = useState(true);
  const [language, setLanguage] = useState('en');

  // Multilingual translations
  const translations = {
    en: {
      title: 'PING PONG',
      subtitle: 'Home Club Scoreboard',
      latestMatch: 'Latest Match',
      player1: 'Player 1',
      player2: 'Player 2',
      passes: 'Passes',
      topPasses: 'Top Passes',
      playerRankings: 'Player Rankings',
      rank: 'Rank',
      player: 'Player',
      wins: 'Wins',
      totalPasses: 'Total Passes',
      avgPasses: 'Avg Passes',
      matches: 'matches',
      lastUpdated: 'Last updated:'
    },
    es: {
      title: 'PING PONG',
      subtitle: 'Marcador del Club Hogar',
      latestMatch: 'Último Partido',
      player1: 'Jugador 1',
      player2: 'Jugador 2',
      passes: 'Pases',
      topPasses: 'Máximos Pases',
      playerRankings: 'Clasificación de Jugadores',
      rank: 'Posición',
      player: 'Jugador',
      wins: 'Victorias',
      totalPasses: 'Total de Pases',
      avgPasses: 'Promedio Pases',
      matches: 'partidos',
      lastUpdated: 'Última actualización:'
    }
  };

  const t = translations[language];

  // Hardcoded sample data
  const [gameData] = useState({
    recentGames: [
      {
        id: 1,
        player1: 'Alex Chen',
        player2: 'Marcus Johnson',
        score1: 11,
        score2: 8,
        date: '2024-03-26',
        time: '6:45 PM',
        passes1: 87,
        passes2: 64
      },
      {
        id: 2,
        player1: 'Sarah Williams',
        player2: 'James Rodriguez',
        score1: 11,
        score2: 9,
        date: '2024-03-26',
        time: '5:20 PM',
        passes1: 92,
        passes2: 88
      },
      {
        id: 3,
        player1: 'Alex Chen',
        player2: 'Sarah Williams',
        score1: 6,
        score2: 11,
        date: '2024-03-25',
        time: '7:10 PM',
        passes1: 71,
        passes2: 98
      }
    ],
    players: [
      { id: 1, name: 'Alex Chen', wins: 12, passes: 1087, matches: 15 },
      { id: 2, name: 'Sarah Williams', wins: 11, passes: 1142, matches: 14 },
      { id: 3, name: 'Marcus Johnson', wins: 9, passes: 945, matches: 14 },
      { id: 4, name: 'James Rodriguez', wins: 8, passes: 912, matches: 13 },
      { id: 5, name: 'Emily Chen', wins: 7, passes: 834, matches: 12 },
      { id: 6, name: 'David Park', wins: 6, passes: 756, matches: 11 }
    ]
  });

  // Calculate most passes from recent games
  const mostPasses = useMemo(() => {
    const passesMap = {};
    gameData.recentGames.forEach(game => {
      passesMap[game.player1] = (passesMap[game.player1] || 0) + game.passes1;
      passesMap[game.player2] = (passesMap[game.player2] || 0) + game.passes2;
    });
    
    const sorted = Object.entries(passesMap).sort((a, b) => b[1] - a[1]);
    return sorted.slice(0, 3).map(([name, passes]) => ({ name, passes }));
  }, [gameData]);

  // Theme color definitions
  const colors = isDark ? {
    bg: 'from-slate-950 via-slate-900 to-slate-950',
    card: 'from-slate-800 to-slate-900',
    cardBorder: 'border-slate-700/50',
    secondaryBg: 'bg-slate-900/60',
    secondaryBorder: 'border-slate-700/30',
    tertiaryBg: 'bg-slate-900/40',
    text: 'text-white',
    label: 'text-slate-400',
    subtext: 'text-slate-500',
    button: 'bg-slate-800 hover:bg-slate-700 text-amber-400',
    buttonBorder: 'border-slate-700'
  } : {
    bg: 'from-slate-50 via-white to-slate-100',
    card: 'from-white to-slate-50',
    cardBorder: 'border-slate-300/50',
    secondaryBg: 'bg-slate-100/60',
    secondaryBorder: 'border-slate-300/30',
    tertiaryBg: 'bg-slate-100/40',
    text: 'text-slate-900',
    label: 'text-slate-600',
    subtext: 'text-slate-500',
    button: 'bg-slate-200 hover:bg-slate-300 text-slate-700',
    buttonBorder: 'border-slate-300'
  };

  return (
    <div className={`min-h-screen transition-colors duration-300 p-4 sm:p-6 lg:p-8 bg-gradient-to-br ${colors.bg} ${colors.text}`}>
      {/* Controls Bar */}
      <div className="flex justify-between items-center mb-6">
        {/* Language Selector */}
        <div className="flex items-center gap-2">
          <Globe className="w-5 h-5" />
          <select
            value={language}
            onChange={(e) => setLanguage(e.target.value)}
            className={`px-3 py-2 rounded-lg transition-all duration-300 text-sm font-semibold border ${colors.button} ${colors.buttonBorder} cursor-pointer`}
          >
            <option value="en">English</option>
            <option value="es">Español</option>
          </select>
        </div>

        {/* Theme Toggle Button */}
        <button
          onClick={() => setIsDark(!isDark)}
          className={`p-3 rounded-lg transition-all duration-300 ${colors.button} border ${colors.buttonBorder}`}
        >
          {isDark ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
        </button>
      </div>

      {/* Header */}
      <div className="mb-8 text-center sm:text-left">
        <div className="flex items-center justify-center sm:justify-start gap-3 mb-2">
          <div className={`w-2 h-8 bg-gradient-to-b ${isDark ? 'from-cyan-400 to-blue-500' : 'from-cyan-500 to-blue-600'}`}></div>
          <h1 className="text-3xl sm:text-4xl font-black tracking-tighter" style={{ fontFamily: 'system-ui' }}>
            {t.title}
          </h1>
        </div>
        <p className={`text-sm tracking-widest uppercase font-semibold ${colors.label}`}>{t.subtitle}</p>
      </div>

      {/* Main Content - Portrait Phone Centered */}
      <div className="flex justify-center">
        <div className="w-full max-w-md space-y-6 mb-8">
          {/* Latest Game Result */}
          <div className={`bg-gradient-to-br ${colors.card} rounded-xl p-6 border ${colors.cardBorder} backdrop-blur-sm`}>
            <div className="flex items-center gap-2 mb-6">
              <Trophy className="w-5 h-5 text-amber-400" />
              <h2 className={`text-sm font-bold tracking-widest uppercase ${colors.label}`}>{t.latestMatch}</h2>
            </div>

            {gameData.recentGames.length > 0 && (
              <div className="space-y-6">
                {/* Score Display */}
                <div className={`${colors.secondaryBg} rounded-lg p-8 border ${colors.secondaryBorder}`}>
                  <div className="flex items-center justify-between gap-4 mb-2">
                    <div className="flex-1">
                      <p className={`${colors.label} text-xs font-semibold tracking-wider uppercase mb-2`}>{t.player1}</p>
                      <p className={`text-2xl font-black ${colors.text} truncate`}>{gameData.recentGames[0].player1}</p>
                    </div>
                    <div className="flex items-center gap-4">
                      <div className="text-right">
                        <div className="text-5xl font-black text-cyan-400 tabular-nums">{gameData.recentGames[0].score1}</div>
                      </div>
                      <div className={`${colors.subtext} text-lg font-bold`}>:</div>
                      <div className="text-left">
                        <div className="text-5xl font-black text-blue-400 tabular-nums">{gameData.recentGames[0].score2}</div>
                      </div>
                    </div>
                    <div className="flex-1 text-right">
                      <p className={`${colors.label} text-xs font-semibold tracking-wider uppercase mb-2`}>{t.player2}</p>
                      <p className={`text-2xl font-black ${colors.text} truncate`}>{gameData.recentGames[0].player2}</p>
                    </div>
                  </div>
                  
                  <div className={`flex items-center justify-between ${colors.label} text-sm mt-4 pt-4 border-t ${colors.secondaryBorder}`}>
                    <span>{gameData.recentGames[0].date}</span>
                    <span>{gameData.recentGames[0].time}</span>
                  </div>
                </div>

                {/* Passes Stats */}
                <div className="grid grid-cols-2 gap-4">
                  <div className={`${colors.tertiaryBg} rounded-lg p-4 border ${colors.secondaryBorder}`}>
                    <p className={`${colors.label} text-xs font-semibold tracking-wider uppercase mb-1`}>{t.passes}</p>
                    <p className="text-2xl font-black text-cyan-400">{gameData.recentGames[0].passes1}</p>
                    <p className={`${colors.subtext} text-xs mt-2`}>{gameData.recentGames[0].player1}</p>
                  </div>
                  <div className={`${colors.tertiaryBg} rounded-lg p-4 border ${colors.secondaryBorder}`}>
                    <p className={`${colors.label} text-xs font-semibold tracking-wider uppercase mb-1`}>{t.passes}</p>
                    <p className="text-2xl font-black text-blue-400">{gameData.recentGames[0].passes2}</p>
                    <p className={`${colors.subtext} text-xs mt-2`}>{gameData.recentGames[0].player2}</p>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Most Passes Card */}
          <div className={`bg-gradient-to-br ${colors.card} rounded-xl p-6 border ${colors.cardBorder} backdrop-blur-sm`}>
            <div className="flex items-center gap-2 mb-6">
              <Zap className="w-5 h-5 text-yellow-400" />
              <h2 className={`text-sm font-bold tracking-widest uppercase ${colors.label}`}>{t.topPasses}</h2>
            </div>

            <div className="space-y-4">
              {mostPasses.map((entry, idx) => (
                <div key={idx} className={`${colors.tertiaryBg} rounded-lg p-4 border ${colors.secondaryBorder}`}>
                  <div className="flex items-center justify-between mb-2">
                    <p className={`font-bold ${colors.text} text-sm`}>{entry.name}</p>
                    <span className="text-xl font-black text-amber-400">{entry.passes}</span>
                  </div>
                  <div className={`w-full ${isDark ? 'bg-slate-800' : 'bg-slate-300'} rounded-full h-1.5 overflow-hidden`}>
                    <div 
                      className="h-full bg-gradient-to-r from-amber-400 to-orange-500 rounded-full"
                      style={{ width: `${(entry.passes / mostPasses[0].passes) * 100}%` }}
                    ></div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Players Stats Table */}
          <div className={`bg-gradient-to-br ${colors.card} rounded-xl overflow-hidden border ${colors.cardBorder} backdrop-blur-sm`}>
            <div className={`p-6 border-b ${colors.secondaryBorder}`}>
              <div className="flex items-center gap-2">
                <Users className="w-5 h-5 text-emerald-400" />
                <h2 className={`text-sm font-bold tracking-widest uppercase ${colors.label}`}>{t.playerRankings}</h2>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className={`border-b ${colors.secondaryBorder} ${isDark ? 'bg-slate-900/40' : 'bg-slate-100/40'}`}>
                    <th className={`px-4 py-3 text-left text-xs font-bold tracking-widest uppercase ${colors.label}`}>{t.rank}</th>
                    <th className={`px-4 py-3 text-left text-xs font-bold tracking-widest uppercase ${colors.label}`}>{t.player}</th>
                    <th className={`px-4 py-3 text-right text-xs font-bold tracking-widest uppercase ${colors.label}`}>{t.wins}</th>
                    <th className={`px-4 py-3 text-right text-xs font-bold tracking-widest uppercase ${colors.label}`}>{t.totalPasses}</th>
                  </tr>
                </thead>
                <tbody>
                  {gameData.players.map((player, idx) => (
                    <tr 
                      key={player.id}
                      className={`border-b ${colors.secondaryBorder} ${isDark ? 'hover:bg-slate-800/40' : 'hover:bg-slate-200/40'} transition-colors`}
                    >
                      <td className="px-4 py-3">
                        <div className={`flex items-center justify-center w-6 h-6 rounded-full ${isDark ? 'bg-cyan-400/20 border border-cyan-400/30' : 'bg-cyan-500/20 border border-cyan-500/30'}`}>
                          <span className="text-xs font-black text-cyan-400">{idx + 1}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3">
                        <p className={`font-bold ${colors.text} text-sm`}>{player.name}</p>
                        <p className={`text-xs ${colors.subtext}`}>{player.matches} {t.matches}</p>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-1">
                          <Trophy className="w-4 h-4 text-amber-400" />
                          <span className={`font-black ${colors.text}`}>{player.wins}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <span className="font-black text-cyan-400">{player.passes}</span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      {/* Footer */}
      <div className="text-center mt-8">
        <p className={`text-xs tracking-widest uppercase font-semibold ${colors.label}`}>
          {t.lastUpdated} {new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
        </p>
      </div>
    </div>
  );
}
