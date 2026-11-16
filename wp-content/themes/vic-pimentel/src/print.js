const chalk = require('chalk');

/**
 * Print a message to the console.
 */
const print = (context, contextColor = 'white') => (message, type = 'info') => {
    const logColors = {
        info: 'white',
        error: 'red',
        success: 'green',
        warning: 'yellow',
        debug: 'magenta',
    };

    let msg = chalk[logColors[type]](message);

    if (type === 'error') {
        msg = chalk.bold(msg);
    }

    if (context) {
        context = chalk[contextColor](context);
        msg = chalk.bold(`[${context}]`) + ' ' + msg;
    }

    console.log(msg);

    return msg;
};

module.exports = print;
