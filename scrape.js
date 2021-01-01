import crawler from '@pierreminiggio/youtube-channel-twitter-handle-crawler'
import { argv}  from 'process'

if (argv.length !== 3) {
    throw new Error('Use like this: node scrape.js [youtubeChannelId]')
}

try {
    console.log(await crawler(argv[2]))
} catch (e) {
    console.log('not found')
}
