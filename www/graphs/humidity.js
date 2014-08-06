// LamPI, Javascript/jQuery GUI for controlling 434MHz devices (e.g. klikaanklikuit, action, alecto)
// Author: M. Westenberg (mw12554 @ hotmail.com)
// (c) M. Westenberg, all rights reserved
//
// Contributions:
//
// Version 1.6, Nov 10, 2013. Implemented connections, started with websockets option next (!) to .ajax calls.
// Version 1.7, Dec 10, 2013. Work on the mobile version of the program
// Version 1.8, Jan 18, 2014. Start support for (weather) sensors
// Version 1.9, Mar 10, 2014, Support for wired sensors and logging, and internet access ...
// Version 2.0, Jun 15, 2014, Initial support for Z-Wave devices through Razberry slave device.
//
// This is the code to animate the front-end of the application. The main screen is divided in 3 regions:
//
// Copyright, Use terms, Distribution etc.
// =========================================================================================
//  This software is licensed under GNU Public license as detailed in the root directory
//  of this distribution and on http://www.gnu.org/licenses/gpl.txt
//
//  The above copyright notice and this permission notice shall be included in
//  all copies or substantial portions of the Software.
// 
//  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
//  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
//  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
//  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
//  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
//  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
//  THE SOFTWARE.
//
//    You should have received a copy of the GNU General Public License
//    along with LamPI.  If not, see <http://www.gnu.org/licenses/>.
//
//
// #gui_header: Here are the buttons for selecting the active rooms 
// #gui_content: The main screen where user can change the values of the active devices for the curently
//		active room. For the moment, the devices can have 2 types: switches and dimmers (see below)
// #gui_mssages: This is the area where the function message("text") will output its messages
// #gui_menu: This is the right side of the screen where we have the main menu where user can select the 
//		main activities: <home> <rooms> <scenes> <timers> <settings>
//
// Function init, Initialises variables as far as needed
// NOTE:: VAriables can be changed in the index.html file before calling start_LAMP
//
// DIV class="class_name"  corresponds to .class_name in CSS
// DIV id="id_name" corresponds to #id_name above
//

// DESIGN NOTES
//
// The Javascript file uses both the jQuery UI for the regular web based execution
// as well as the jQuery Mobile functions for Android/PhoneGAP.
// Unfortunately, widgets, functions etc differ between these two libraries (which is a SHAME).
// It is expected that jQueryUI and jQmobile will merge in next release.
// Therefore, I did not make separate files but one file with conditional execution of dialogs etc.
// Meanwhile, this file will support both libs for their separate purposes.


// ----------------------------------------------------------------------------
// SETTINGS!!!
// XXX: For adaptation to jqmobile changed all pathnames of backend to absolute URL's
//

function start_TEMPI(){
	$(window).load(function(){
							
							
							
	});
	console.log("Start TEMPI done");
	init();
}

function init() {
	console.log("init started");
}