<script>
function getNumWeeksForMonth(year, month) {
    date = new Date(year, month - 1, 1);
    day = date.getDay();
    numDaysInMonth = new Date(year, month, 0).getDate();
    return Math.ceil((numDaysInMonth + day) / 7);
}

function fetch_select(val) {
    /** 
     * Get the ISO week date week number 
     */
    Date.prototype.getWeek = function() {
        // Create a copy of this date object  
        var target = new Date(this.valueOf());

        // ISO week date weeks start on monday  
        // so correct the day number  
        var dayNr = (this.getDay() + 6) % 7;

        // ISO 8601 states that week 1 is the week  
        // with the first thursday of that year.  
        // Set the target date to the thursday in the target week  
        target.setDate(target.getDate() - dayNr + 3);

        // Store the millisecond value of the target date  
        var firstThursday = target.valueOf();

        // Set the target to the first thursday of the year  
        // First set the target to january first  
        target.setMonth(0, 1);
        // Not a thursday? Correct the date to the next thursday  
        if (target.getDay() != 4) {
            target.setMonth(0, 1 + ((4 - target.getDay()) + 7) % 7);
        }

        // The weeknumber is the number of weeks between the   
        // first thursday of the year and the thursday in the target week  
        return 1 + Math.ceil((firstThursday - target) / 604800000); // 604800000 = 7 * 24 * 3600 * 1000  
    }
    tempval = val.period_month.value.split(/\//);
    month = tempval[0];
    year = tempval[1];
    alert(getNumWeeksForMonth(year, month));
    newdate = year + ',' + month + ',01';
    var today = new Date(newdate);
    week1 = today.getWeek();
    alert(week1);


    val.period_week.value = week1;
}
</script>