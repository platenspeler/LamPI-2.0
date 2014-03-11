/*
  Copyright (c) 2013,2014 Maarten Westenberg, mw12554@hotmail.com 
 
  This software is licensed under GNU license as detailed in the root directory
  of this distribution and on http://www.gnu.org/licenses/gpl.txt
 
  The above copyright notice and this permission notice shall be included in
  all copies or substantial portions of the Software.
 
  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
  THE SOFTWARE.
*/
#include <wiringPi.h>  
#include <stdio.h>  
#include <stdlib.h> 
#include <string.h>
#include <stdint.h> 
#include <time.h>
#include <errno.h>
#include <netdb.h>
#include <math.h>
#include <unistd.h>
#include <sys/types.h>
#include <netinet/in.h>
#include <sys/socket.h>
#include <arpa/inet.h>
#include <dirent.h>

#include "cJSON.h"
#include "sensor.h"


/* ----------------------------------------------------------------
 * The DS18B20 is a cheap yet powerful temperature sensor.
 * It works over the 1-wire Dallas bus
 * The Raspberry provides module support for the Dallas/Maxim bus
 * Make sure you load the w1-gpio and w1-therm modules.
 * > sudo modprobe w1-therm
 * > sodu modprobe w1-gpio
 * By reading the approrpiate device entry w1_slave the module will
 * return the sensor value
 *
 * ----------------------------------------------------------------	*/

// Declaration for Statistics
int statistics [I_MAX_ROWS][I_MAX_COLS];

// Declarations for Sockets
int sockfd;	
fd_set fds;
int socktcnt = 0;

// Options flags
static int dflg = 0;							// Daemon
static int cflg = 0;							// Check
int verbose = 0;
int debug = 0;
int checks = 0;									// Number of checks on messages
int sflg = 0;									// If set, gather statistics

/*
 *********************************************************************************
 * INIT STATISTICS
 *
 *********************************************************************************/
int init_statistics(int statistics[I_MAX_ROWS][I_MAX_COLS])
{
	// Brute force method. Just make everything 0
	int i;
	int j;
	for (i=0; i<I_MAX_ROWS; i++)
	{
		for (j=0; j<I_MAX_COLS; j++)
		{
			statistics[i][j]=0;
		}
	}
	return(0);
} 

/*
 *********************************************************************************
 * Get In Addr
 *
 * get sockaddr, IPv4 or IPv6: These new way of dealing with sockets in Linux/C 
 * makes use of structs.
 *********************************************************************************
 */
void *get_in_addr(struct sockaddr *sa)
{
    if (sa->sa_family == AF_INET) {
        return &(((struct sockaddr_in*)sa)->sin_addr);
    }
    return &(((struct sockaddr_in6*)sa)->sin6_addr);
}

/*
 *********************************************************************************
 * Open Socket
 * The socket is used both by the sniffer and the transmitter program.
 * Standard communication is on port 5000 over websockets.
 *********************************************************************************
 */
int open_socket(char *host, char *port) {

	int sockfd;  
    struct addrinfo hints, *servinfo, *p;
    int rv;
    char s[INET6_ADDRSTRLEN];

    memset(&hints, 0, sizeof hints);
    hints.ai_family = AF_UNSPEC;
    hints.ai_socktype = SOCK_STREAM;

    if ((rv = getaddrinfo(host, port, &hints, &servinfo)) != 0) {
        fprintf(stderr, "getaddrinfo: %s %s\n", host, gai_strerror(rv));
        return -1;
    }

    // loop through all the results and connect to the first we can
	
    for(p = servinfo; p != NULL; p = p->ai_next) {
	
        if ((sockfd = socket(p->ai_family, p->ai_socktype,
                p->ai_protocol)) == -1) {
            perror("client: socket");
            continue;
        }

        if (connect(sockfd, p->ai_addr, p->ai_addrlen) == -1) {
            close(sockfd);
			fprintf(stderr,"Address: %s, ", (char *) p->ai_addr);
            perror("client: connect");
            continue;
        }

        break;
    }

    if (p == NULL) {
        fprintf(stderr, "client: failed to connect\n");
        return -1;
    }

    inet_ntop(p->ai_family, get_in_addr((struct sockaddr *)p->ai_addr), s, sizeof s);
    printf("client: connecting to %s\n", s);

    freeaddrinfo(servinfo); // all done with this structure
	
	return(sockfd);
}


/*
 *********************************************************************************
 * DAEMON mode
 * In daemon mode, the interrupt handler will itself post completed messages
 * to the main LamPI-daemon php program. In order to not spend too much wait time 
 * in the main program, we can either sleep or (which is better) listen to the 
 * LamPI-daemoan process for incoming messages to be sent to the transmitter.
 *
 * These messages could be either PINGs or requests to the transmitter to send
 * device messages to the various receiver programs.
 * XXX We could move this function to a separate .c file for readibility
 *********************************************************************************
 */
 
 int daemon_mode(char *hostname, char* port) 
 {
	// ---------------- FOR DAEMON USE, OPEN SOCKETS ---------------------------------
	// If we choose daemon mode, we will need to open a socket for communication
	// This needs to be done BEFORE enabling the interrupt handler, as we want
	// the handler to send its code to the LamPI-daemon process 
	
	// Open a socket
	if ((sockfd = open_socket(hostname,port)) < 0) {
		fprintf(stderr,"Error opening socket for host %s. Exiting program\n\n", hostname);
		exit (1);
	};
	FD_ZERO(&fds);
	FD_SET(sockfd, &fds);

	return(0);
}


/*
 *********************************************************************************
 * ds18b20_read
 *
 * Reading the sensor using the power method. Call the wait routine several times.
 * this method is more compute intensive than calling the interrupt routine.
 *
 *********************************************************************************
 */ 
int ds18b20_read(char *dir)  
{  
	char dev[128];
	char line[128];
	FILE *fp;
	int temperature;
	char * tpos = NULL;
	char * crcpos = NULL;
	//int cntr = 0;

	
	strcpy(dev,SPATH);
	strcat(dev,"/");
	strcat(dev,dir);
	strcat(dev,"/w1_slave");
	
	if (verbose) printf("dev: %s\n",dev);
	
	if (NULL == (fp = fopen(dev,"r") )) {
		perror("Error opening device");
		return(-1);
	}
	
	while (fgets(line, 128, fp) != NULL )
	{
		if (verbose) printf("read line: %s", line);
		
		// Before we read a temperature, first check the crc which is in a
		// line before the actual temperature line
		if (crcpos == NULL) {
			crcpos = strstr(line, "crc=");
			if ((crcpos != NULL) && (strstr(crcpos,"YES") == NULL)) {
				// CRC error
				crcpos = NULL;
				if (verbose) fprintf(stderr,"crc error for device %s\n" , dev);
			}
		}
		
		// If we have a valid crc check on a line, next line will contain valid temperature
		else {
			if (verbose) printf("crc read correctly\n");
			tpos = strstr(line, "t=");
			// Will only be true for 2nd line
			if (tpos != NULL) {
				tpos +=2;
				sscanf(tpos, "%d", &temperature);
			}
			if (verbose) printf("temp read: %d\n\n",temperature);
		}
		
	}
	
	fclose(fp);
	//
	
	return(temperature);
} 


/* ********************************************************************
 * MAIN PROGRAM
 *
 * Read the user option of the commandline and either print to stdout
 * or return the value over the socket. 
 *
 * ********************************************************************	*/  
int main(int argc, char **argv)  
{  
	int i,c;
	int errflg = 0;
	int repeats = 1;
	int temp = 0;
	int temp_int, temp_frac;				// interger and fracture part for temperature
	
	char *hostname = "localhost";			// Default setting for our host == this host
	char *port = PORT;						// default port, 5000
	char snd_buf[256];
	
    extern char *optarg;
    extern int optind, optopt;

	// ------------------------- COMMANDLINE OPTIONS SETTING ----------------------
	// Valid options are:
	// -h <hostname> ; hostname or IP address of the daemon
	// -p <port> ; Portnumber for daemon socket
	// -v ; Verbose, Give detailed messages
	//
    while ((c = getopt(argc, argv, ":c:dh:p:r:stvx")) != -1) {
        switch(c) {

		case 'c':
			cflg = 1;					// Checks
			checks = atoi(optarg);
		break;
		case 'd':						// Daemon mode, cannot be together with test?
			dflg = 1;
		break;
		case 'h':						// Socket communication
            dflg++;						// Need daemon flag too, (implied)
			hostname = optarg;
		break;
		case 'p':						// Port number
            port = optarg;
           dflg++;						// Need daemon flag too, (implied)
        break;
		case 'r':						// repeats
			repeats = atoi(optarg);
		break;
		case 's':						// Statistics
			sflg = 1;
		break;
		case 't':						// Test Mode, do debugging
			debug=1;
		break;
		case 'v':						// Verbose, output long timing/bit strings
			verbose = 1;
		break;
		case ':':       				// -f or -o without operand
			fprintf(stderr,"Option -%c requires an operand\n", optopt);
			errflg++;
		break;
		case '?':
			fprintf(stderr, "Unrecognized option: -%c\n", optopt);
            errflg++;
        }
    }
	
	// -------------------- PRINT ERROR ---------------------------------------
	// Print error message if parsing the commandline
	// was not successful
	
    if (errflg) {
        fprintf(stderr, "usage: argv[0] (options) \n\n");
		
		fprintf(stderr, "-d\t\t; Daemon mode. Codes received will be sent to another host at port 5000\n");
		fprintf(stderr, "-s\t\t; Statistics, will gather statistics from remote\n");
		fprintf(stderr, "-t\t\t; Test mode, will output received code from remote\n");
		fprintf(stderr, "-v\t\t; Verbose, will output more information about the received codes\n");
        exit (2);
    }
	

	//	------------------ PRINTING Parameters ------------------------------
	//
	if (verbose == 1) {
		printf("The following options have been set:\n\n");
		printf("-v\t; Verbose option\n");
		if (statistics>0)	printf("-s\t; Statistics option\n");
		if (dflg>0)			printf("-d\t; Daemon option\n");
		if (debug)			printf("-t\t; Test and Debug option");
		printf("\n");						 
	}//if verbose
	
	// If we are in daemon mode, initialize sockets etc.
	//
	if (dflg) {
		daemon_mode(hostname, port);
	}
	
	if (sflg) {
		fprintf(stderr,"init statistics\n");
		init_statistics(statistics);			// Make cells 0
	}
	
	chdir (SPATH);
	
	// ------------------------
	// MAIN LOOP
	// 

	if (verbose) printf("\nRepeats: %d::\n",repeats);
	for (i=0; i<repeats; i++)  
	{  
		// For every directory found in SPATH
		DIR *dir;
		struct dirent *ent;
		if ((dir = opendir (SPATH)) != NULL) {
			/* print all the files and directories within directory */
			while ((ent = readdir (dir)) != NULL) {
				if (verbose) printf ("%s\n", ent->d_name);
				// 28 is the prefix for ds18b20
				if (strncmp(ent->d_name,"28",2) == 0)
				{
					temp = ds18b20_read(ent->d_name);
					temp_int = temp/1000;
					temp_frac = temp%1000;
					
					if (dflg) {
					// Daemon, output to socket
						sprintf(snd_buf, 					 "{\"tcnt\":\"%d\",\"action\":\"weather\",\"brand\":\"ds18b20\",\"type\":\"json\",\"address\":\"%s\",\"channel\":\"%d\",\"temperature\":\"%d.%d\",\"humidity\":\"%d\",\"windspeed\":\"%d\",\"winddirection\":\"%d\"}", 
						socktcnt%1000,
						ent->d_name,
						0,
						temp_int,
						temp_frac,
						0,
						0,
						0);
					
						// Do NOT use check_n_write_socket as weather stations will not
						// send too many repeating messages (1 or 2 will come in one trasmission)
						//
						if (write(sockfd, snd_buf, strlen(snd_buf)) == -1) {
							fprintf(stderr,"socket write error\n");
						}	
						socktcnt++;
						delay(200);
						
						if (verbose) printf("Buffer sent to Socket: %s\n",snd_buf);
					}
					else {
					// Commandline
						if (temp > 0) {
							printf("Temperature for dev %s: %d.%d\n",
								ent->d_name, temp/1000,temp%1000);
						}
						else {
							temp = -temp;
							printf("Temperature for dev %s: -%d.%d\n",
								ent->d_name, temp/1000,temp%1000);
						}
					}
				}
			}
			closedir (dir);
		} else {
  			/* could not open directory */
 			 perror ("No such directory ");
			return EXIT_FAILURE;
		}
	}
	delay(1500);
	// Should wait for confirmation of the daemon before closing
	
	exit(EXIT_SUCCESS); 
}  
